# BioBridge (puente 32-bit para SDK ZKTECO)

Este mini-proyecto crea un servicio HTTP local (x86) que expone un endpoint para capturar huellas usando la librería `libzkfpcsharp` x86 incluida en `ejemplos`.

Endpoint:
- POST http://localhost:5101/capture  -> respuesta JSON { success, template, imagen }

Requisitos:
- Visual Studio (compilar como x86). 
- `ejemplos/C#/lib/x86/libzkfpcsharp.dll` debe existir (ya incluido en el repo).

Compilar y ejecutar:

1. Abrir `tools/biobridge/BioBridge.csproj` en Visual Studio.
2. Seleccionar plataforma `x86` y compilar (Release o Debug).
3. Ejecutar el exe resultante (ej: `bin\Debug\BioBridge.exe`).

Prueba desde PowerShell/PHP:
```powershell
Invoke-RestMethod -Uri 'http://localhost:5101/capture' -Method Post
```

Integración con PHP:
- `datos/BiometricSensor::capturarHuella()` intentará primero llamar al puente en `http://127.0.0.1:5101/capture` y, si está disponible, usará su respuesta.
- Si el puente no está disponible, PHP intentará el camino COM (solo funciona si PHP/Apache 32-bit y OCX registrado).

Notas:
- Ejecuta el exe con el usuario que tenga permisos a los dispositivos USB.
- Si necesitas que lo ejecute aquí, indícame y guío los pasos para compilar en tu máquina.
