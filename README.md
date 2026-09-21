**Resumen**
- **Descripción:**: Sistema de control de asistencia basado en huella dactilar que combina una aplicación web PHP/MySQL con un puente local (puente .NET) que usa el SDK ZKTECO (`libzkfpcsharp`) para acceder al lector biométrico.
- **Componentes principales:**: Frontend/PHP, `datos/BiometricSensor.php`, proceso de captura `proceso/capturar_huella.php`, vistas en `vistas/`, y el puente 32-bit en `tools/biobridge`.

**Requisitos Previos**
- **SO:**: Windows (desarrollado y probado en Windows 10/11).
- **Servidor web:**: XAMPP (Apache + PHP) instalado y configurado.
- **.NET / MSBuild:**: Visual Studio o MSBuild para compilar el puente (`BioBridge.csproj`).
- **SDK ZKTECO:**: `libzkfpcsharp.dll` (versión x86) incluida en `ejemplos/C#/lib/x86/`.
- **Lector biométrico:**: Lector ZKTECO compatible y drivers instalados (USB).
- **Acceso a puertos/firewall:**: Permitir puerto elegido para puente (por defecto `5101`).

**Estructura de archivos clave**
- **Puente .NET:**: [tools/biobridge/Program.cs](tools/biobridge/Program.cs#L1-L1)
- **README del puente:**: [tools/biobridge/README.md](tools/biobridge/README.md#L1-L1)
- **Gestión de huellas (PHP):**: [datos/BiometricSensor.php](datos/BiometricSensor.php#L1-L1)
- **Endpoint de captura/asistencia:**: [proceso/capturar_huella.php](proceso/capturar_huella.php#L1-L1)
- **UI de registro de huella:**: [vistas/guardar_huella.php](vistas/guardar_huella.php#L1-L1)

**Instalación (máquina nueva)**
- **1. Preparar XAMPP:**
  - Instala XAMPP y asegúrate que Apache y MySQL funcionen.
  - Copia este proyecto en `C:\xampp\htdocs\Rtime`.

- **2. PHP: extensiones y configuración:**
  - En `php.ini` habilita `extension=php_com_dotnet.dll` si planeas usar COM (fallback). Reinicia Apache.
  - Asegura `allow_url_fopen = On` para llamadas `file_get_contents()` si se usan.

- **3. Base de datos:**
  - Crea la base de datos y tablas esperadas (la app asume tablas `trabajador`, `huella_biometrica`, `asistencias`).
  - Revisa `datos/db.php` para credenciales y ajústalas.

- **4. SDK y librerías nativas:**
  - Copia `libzkfpcsharp.dll` y otras dependencias x86 a `tools/biobridge` y al proyecto C# según `BioBridge.csproj`.
  - El puente debe compilarse como `x86` porque la mayoría de SDKs son 32-bit.

- **5. Compilar el puente (.NET / MSBuild / Visual Studio):**
  - Opción Visual Studio: abrir `tools/biobridge/BioBridge.csproj`, seleccionar `x86` y compilar (Release).
  - Opción MSBuild (Developer Command Prompt):
    - `msbuild BioBridge.csproj /p:Configuration=Release /p:Platform=x86 /p:BaseOutputPath=bin\\Release\\` (ejecutar en `tools\\biobridge`).
  - El exe compilado queda en `tools/biobridge/bin/Release/BioBridge.exe`.

- **6. Ejecutar el puente (pruebas):**
  - Desde PowerShell (no bloquear la consola):
    - `Start-Process -FilePath "C:\xampp\htdocs\Rtime\tools\biobridge\BioBridge.exe" -WorkingDirectory "C:\xampp\htdocs\Rtime\tools\biobridge"`
  - Alternativa (ver en consola): abrir `cmd` o PowerShell y ejecutar `.
    BioBridge.exe` en la carpeta del proyecto para ver logs.
  - Por defecto el puente escucha en `http://localhost:5101/` (archivos del proyecto y README indican el puerto). Si eliges otro puerto, actualiza `datos/BiometricSensor.php` con la nueva URL.

**Configuración de PHP (integración con el puente)**
- **`datos/BiometricSensor.php`**: este archivo primero intenta llamar al puente en `http://localhost:5101/capture` y `http://localhost:5101/compare` mediante `file_get_contents()` con `Content-Type: application/json`.
- **Ajustes a comprobar:**
  - `allow_url_fopen` habilitado.
  - Si Apache/PHP corre en 64-bit y necesitas COM/OCX, la ruta COM no funcionará (COM requiere PHP 32-bit y OCX registrado) → por eso el puente es la opción recomendada.

**Flujo de uso (resumen)**
- Usuario abre `vistas/guardar_huella.php` → pulsa Capturar → el cliente llama `proceso/capturar_huella.php` → este invoca `BiometricSensorManager::capturarHuella()` → puente devuelve `template` y `imagen` → guardar en `huella_biometrica`.
- Proceso de asistencia: `proceso/capturar_huella.php` en modo asistencia captura template vivo, llama `BiometricSensorManager::verificarAsistencia()` que itera plantillas DB y para cada una llama al puente `/compare` para obtener `score` y decidir coincidencia.

**Puente y puertos / HTTP.SYS**
- **Reservas URL y conflictos:**: si `HttpListener` lanza error 404/Conflict al arrancar, puede deberse a una `urlacl` existente o a que otro servicio (HTTP.SYS) intercepta el puerto. Revisa con:
  - `netsh http show urlacl`
  - `netstat -ano | findstr :5101` para ver qué PID usa el puerto.
  - Si hay conflicto, elige otro puerto (ej.: 5101 es el recomendado por el proyecto; puedes usar 5201, 5301, etc.).

**Depuración y errores comunes**
- **404 Microsoft-HTTPAPI/2.0**: suele indicar que `HTTP.SYS` respondió (prefijo reservado) o que el puente no está corriendo. Solución: comprobar proceso en `tasklist`, `Get-CimInstance`, y reiniciar el exe compilado.
- **Timeout waiting for finger**: el puente no detectó dedo dentro del tiempo (ajusta tiempo o coloca el dedo). Verifica conexión del lector y drivers.
- **score: 0 al comparar plantilla idéntica**: comprobar que las plantillas en BD y la capturada usan exactamente la misma codificación (Base64) y longitud; revisar que el puente decodifica `Convert.FromBase64String(...)` correctamente y que la plantilla en DB no sufrió transformaciones (saltos de línea, encoding, doble base64).
- **file_get_contents(...) Failed to open stream: HTTP/1.1 404 Not Found**: PHP no encuentra el puente en la URL indicada; confirma puerto, arranque y que `allow_url_fopen` esté activo o usa `curl` en PHP.

**Seguridad y permisos**
- **Ejecutar puente:**: si el exe necesita acceso a dispositivos USB, lanza el exe con un usuario que tenga permisos a los dispositivos (no uses SYSTEM salvo que sepas lo que haces).
- **Firewall:**: abrir el puerto local solo si es necesario; en general el puente escucha en `localhost` y no debería exponerse a la red.
- **Acceso a plantillas:**: las plantillas biométricas son datos sensibles — restringe acceso a la BD, usa backups en lugar de exportar raw templates.

**Mantenimiento y buenas prácticas**
- **Backups:**: respalda la tabla `huella_biometrica` y `trabajador` periódicamente.
- **Logs:**: habilitar logs en el puente temporalmente para depurar, pero evita guardar plantillas en texto plano en logs en producción.
- **Actualizaciones:**: cuando actualices `Program.cs` recuerda recompilar `BioBridge.exe` y reemplazar el ejecutable en la carpeta raíz del puente.

**Comandos útiles**
- Iniciar puente en background:
  - `Start-Process -FilePath "C:\xampp\htdocs\Rtime\tools\biobridge\BioBridge.exe" -WorkingDirectory "C:\xampp\htdocs\Rtime\tools\biobridge"`
- Ver qué escucha en puerto:
  - `netstat -ano | findstr :5101`
- Ver reservas URL:
  - `netsh http show urlacl`
- Compilar con MSBuild (si MSBuild instalado):
  - `msbuild BioBridge.csproj /p:Configuration=Release /p:Platform=x86 /p:BaseOutputPath=bin\\Release\\` (ejecutar en `tools\\biobridge`).

**Checklist antes de instalar en otra máquina**
- **Hardware:** lector compatible y drivers instalados.
- **Platform:** Windows con .NET/MSBuild o Visual Studio para compilar.
- **XAMPP:** Apache/PHP/MySQL configurado.
- **Permisos:** privilegios para abrir puertos y acceder a dispositivos USB.
- **Puerto libre:** elegir puerto para el puente (recomiendo `5101` o `5201` si hay conflictos).

**Archivo de referencia**
- `datos/BiometricSensor.php`: lógica de fallback puente → COM y funciones de comparación.
- `tools/biobridge/Program.cs`: lógica del puente, endpoints `/capture` y `/compare`.
- `vistas/guardar_huella.php`: UI para captura y guardado de plantillas.
- `proceso/capturar_huella.php`: endpoint que orquesta captura y registro de asistencia.

Si quieres, puedo:
- generar un script de instalación paso a paso (PowerShell) que automatice la copia/compilación del puente y la creación de la DB; o
- añadir una sección de `README.md` con procedimientos de verificación (comandos y checks automáticos) para facilitar desplegar en nuevas máquinas.

---
_Última actualización: 20 de julio de 2026_
