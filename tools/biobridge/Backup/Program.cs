using System;
using System.Collections.Generic;
using System.IO;
using System.Net;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using libzkfpcsharp;
using Sample;

namespace BioBridge
{
    class Program
    {
        static void Main(string[] args)
        {
            Console.WriteLine("BioBridge starting on http://localhost:5101/");
            HttpListener listener = new HttpListener();
            listener.Prefixes.Add("http://localhost:5101/");
            listener.Start();

            while (true)
            {
                var ctx = listener.GetContext();
                Task.Run(() => Handle(ctx));
            }
        }

        static void Handle(HttpListenerContext ctx)
        {
            try
            {
                if (ctx.Request.HttpMethod == "POST" && ctx.Request.Url.AbsolutePath.TrimEnd('/') == "/capture")
                {
                    var result = CaptureOnce(10000); // timeout ms
                    var json = ToJson(result);
                    byte[] buf = Encoding.UTF8.GetBytes(json);
                    ctx.Response.ContentType = "application/json; charset=utf-8";
                    ctx.Response.ContentLength64 = buf.Length;
                    ctx.Response.OutputStream.Write(buf, 0, buf.Length);
                }
                else if (ctx.Request.HttpMethod == "POST" && ctx.Request.Url.AbsolutePath.TrimEnd('/') == "/compare")
                {
                    string body;
                    using (var reader = new StreamReader(ctx.Request.InputStream, ctx.Request.ContentEncoding))
                    {
                        body = reader.ReadToEnd();
                    }

                    string tpl1 = null;
                    string tpl2 = null;

                    if (ctx.Request.ContentType != null && ctx.Request.ContentType.Contains("application/json"))
                    {
                        try
                        {
                            var serializer = new JavaScriptSerializer();
                            var parsed = serializer.Deserialize<Dictionary<string, string>>(body);
                            if (parsed != null)
                            {
                                if (parsed.ContainsKey("template1")) tpl1 = parsed["template1"];
                                if (parsed.ContainsKey("template2")) tpl2 = parsed["template2"];
                            }
                        }
                        catch { }
                    }

                    if (tpl1 == null || tpl2 == null)
                    {
                        var form = ParseFormUrlEncoded(body);
                        tpl1 = form.ContainsKey("template1") ? form["template1"] : null;
                        tpl2 = form.ContainsKey("template2") ? form["template2"] : null;
                    }

                    var result = CompareTemplates(tpl1, tpl2);
                    var responseJson = ToJson(result);
                    byte[] buf = Encoding.UTF8.GetBytes(responseJson);
                    ctx.Response.ContentType = "application/json; charset=utf-8";
                    ctx.Response.ContentLength64 = buf.Length;
                    ctx.Response.OutputStream.Write(buf, 0, buf.Length);
                }
                else
                {
                    ctx.Response.StatusCode = 404;
                    ctx.Response.Close();
                }
            }
            catch (Exception ex)
            {
                var err = new CaptureResult { success = false, mensaje = ex.Message };
                var json = ToJson(err);
                var buf = Encoding.UTF8.GetBytes(json);
                ctx.Response.ContentType = "application/json; charset=utf-8";
                ctx.Response.ContentLength64 = buf.Length;
                ctx.Response.OutputStream.Write(buf, 0, buf.Length);
            }
            finally
            {
                try { ctx.Response.OutputStream.Close(); } catch { }
            }
        }

        static CaptureResult CaptureOnce(int timeoutMs)
        {
            int ret = zkfperrdef.ZKFP_ERR_OK;

            if ((ret = zkfp2.Init()) != zkfperrdef.ZKFP_ERR_OK)
            {
                return new CaptureResult { success = false, mensaje = "Init failed: " + ret };
            }

            int devCount = zkfp2.GetDeviceCount();
            if (devCount <= 0)
            {
                zkfp2.Terminate();
                return new CaptureResult { success = false, mensaje = "No device connected" };
            }

            IntPtr dev = zkfp2.OpenDevice(0);
            if (dev == IntPtr.Zero)
            {
                zkfp2.Terminate();
                return new CaptureResult { success = false, mensaje = "OpenDevice failed" };
            }

            // Obtener parámetros de la imagen
            byte[] param = new byte[4];
            int size = 4;
            zkfp2.GetParameters(dev, 1, param, ref size);
            zkfp2.ByteArray2Int(param, ref size);
            int width = size;
            param = new byte[4]; size = 4;
            zkfp2.GetParameters(dev, 2, param, ref size);
            zkfp2.ByteArray2Int(param, ref size);
            int height = size;

            byte[] FPBuffer = new byte[width * height];
            byte[] CapTmp = new byte[2048];
            int cbCapTmp = 2048;

            DateTime start = DateTime.Now;
            while ((DateTime.Now - start).TotalMilliseconds < timeoutMs)
            {
                cbCapTmp = 2048;
                int r = zkfp2.AcquireFingerprint(dev, FPBuffer, CapTmp, ref cbCapTmp);
                if (r == zkfp.ZKFP_ERR_OK)
                {
                    // obtener template base64
                    string tplB64 = zkfp2.BlobToBase64(CapTmp, cbCapTmp);

                    // obtener imagen bmp en memoria
                    using (var ms = new MemoryStream())
                    {
                        BitmapFormat.GetBitmap(FPBuffer, width, height, ms);
                        var imgBytes = ms.ToArray();
                        string imgB64 = Convert.ToBase64String(imgBytes);

                        zkfp2.CloseDevice(dev);
                        zkfp2.Terminate();

                        return new CaptureResult { success = true, template = tplB64, imagen = imgB64, score = 0 };
                    }
                }
                Thread.Sleep(200);
            }

            zkfp2.CloseDevice(dev);
            zkfp2.Terminate();
            return new CaptureResult { success = false, mensaje = "Timeout waiting for finger", score = 0 };
        }

        static CaptureResult CompareTemplates(string tpl1Base64, string tpl2Base64)
        {
            if (string.IsNullOrEmpty(tpl1Base64) || string.IsNullOrEmpty(tpl2Base64))
            {
                return new CaptureResult { success = false, mensaje = "Faltan templates para comparar", score = 0 };
            }

            int ret = zkfperrdef.ZKFP_ERR_OK;
            if ((ret = zkfp2.Init()) != zkfperrdef.ZKFP_ERR_OK)
            {
                return new CaptureResult { success = false, mensaje = "Init failed: " + ret, score = 0 };
            }

            IntPtr dbHandle = zkfp2.DBInit();
            if (dbHandle == IntPtr.Zero)
            {
                zkfp2.Terminate();
                return new CaptureResult { success = false, mensaje = "DBInit failed", score = 0 };
            }

            try
            {
                byte[] t1 = Convert.FromBase64String(tpl1Base64);
                byte[] t2 = Convert.FromBase64String(tpl2Base64);
                int score = zkfp2.DBMatch(dbHandle, t1, t2);

                zkfp2.Terminate();
                return new CaptureResult
                {
                    success = score > 0,
                    mensaje = score > 0 ? "Coincidencia encontrada" : "No coincide",
                    score = score
                };
            }
            catch (Exception ex)
            {
                zkfp2.Terminate();
                return new CaptureResult { success = false, mensaje = ex.Message, score = 0 };
            }
        }

        static Dictionary<string, string> ParseFormUrlEncoded(string body)
        {
            var result = new Dictionary<string, string>();
            if (string.IsNullOrEmpty(body))
            {
                return result;
            }

            foreach (var part in body.Split('&'))
            {
                var chunks = part.Split(new[] { '=' }, 2);
                if (chunks.Length == 2)
                {
                    result[WebUtility.UrlDecode(chunks[0])] = WebUtility.UrlDecode(chunks[1]);
                }
            }

            return result;
        }

        static string EscapeJson(string value)
        {
            if (value == null)
                return "null";

            StringBuilder sb = new StringBuilder();
            sb.Append('"');
            foreach (char c in value)
            {
                switch (c)
                {
                    case '"': sb.Append("\\\""); break;
                    case '\\': sb.Append("\\\\"); break;
                    case '\b': sb.Append("\\b"); break;
                    case '\f': sb.Append("\\f"); break;
                    case '\n': sb.Append("\\n"); break;
                    case '\r': sb.Append("\\r"); break;
                    case '\t': sb.Append("\\t"); break;
                    default:
                        if (c < 32 || c > 126)
                            sb.AppendFormat("\\u{0:X4}", (int)c);
                        else
                            sb.Append(c);
                        break;
                }
            }
            sb.Append('"');
            return sb.ToString();
        }

        static string ToJson(CaptureResult result)
        {
            StringBuilder sb = new StringBuilder();
            sb.Append('{');
            sb.Append("\"success\":"); sb.Append(result.success ? "true" : "false");

            if (result.mensaje != null)
            {
                sb.Append(",\"mensaje\":"); sb.Append(EscapeJson(result.mensaje));
            }
            sb.Append(",\"score\":"); sb.Append(result.score);
            if (result.template != null)
            {
                sb.Append(",\"template\":"); sb.Append(EscapeJson(result.template));
            }
            if (result.imagen != null)
            {
                sb.Append(",\"imagen\":"); sb.Append(EscapeJson(result.imagen));
            }
            sb.Append('}');
            return sb.ToString();
        }

        class CaptureResult
        {
            public bool success;
            public string template;
            public string imagen;
            public string mensaje;
            public int score;
        }
    }
}
