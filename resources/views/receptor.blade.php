<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Receptor</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: system-ui, sans-serif; padding: 24px; }
        .wrap { max-width: 760px; margin: 0 auto; }
        #target-div { padding: 20px; border: 1px dashed #bbb; border-radius: 12px; }
        #estado { font-size: 14px; margin-bottom: 12px; }
        h1 { font-size: 2rem; font-weight: 700; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Receptor</h1>
    <div id="estado">Conexión con Reverb: <strong>conectando…</strong></div>
    <div id="target-div">Esperando mensaje...</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const targetDiv = document.getElementById('target-div');
        const estado = document.querySelector('#estado strong');

        // Estado del WebSocket (connecting → connected / unavailable / disconnected)
        window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => {
            estado.textContent = current;
            estado.style.color = current === 'connected' ? 'green' : 'crimson';
        });

        // Suscripción al canal público y escucha del evento
        window.Echo.channel('style-channel')
            .listen('.RemoteStyleUpdate', (e) => {
                // El nombre con punto (.) escucha usando broadcastAs()
                targetDiv.innerText = e.newText ?? '';
                targetDiv.style.cssText = e.newStyle ?? '';
            });
    });
</script>
</body>
</html>
