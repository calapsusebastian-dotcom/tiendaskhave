import './echo.js';

// Helper global: suscribe a un canal Echo cuando esté disponible.
window.echoWhen = (fn) => {
    if (window.Echo) {
        fn(window.Echo);
    } else {
        window.addEventListener('echo:ready', () => fn(window.Echo), { once: true });
    }
};

// Estado persistente de la estación de impresión.
// Se crea una sola vez; sobrevive la navegación SPA porque vive en window.
if (!window._qz) {
    window._qz = {
        conectado: false,
        impresoras: [],
        impresora: localStorage.getItem('qz_impresora') || '',
        echoListo: false,
        imprimir: null,
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('estacionImpresion', () => {
        const C = window._qz;

        return {
            // Restaurar estado desde caché de forma sincrónica (sin async).
            // Así el componente nunca parpadea a "desconectado" al volver.
            estado:     C.conectado ? 'conectado' : 'desconectado',
            impresoras: C.impresoras.slice(),
            impresora:  C.impresora,
            cola:        [],
            folioManual: '',
            buscando:    false,
            autoImprimir: true,
            errorMsg:    '',

            async init() {
                const C = window._qz;

                // Apuntar handler a esta instancia (se actualiza en cada visita).
                C.imprimir = (data) => {
                    if (this.autoImprimir) {
                        this.imprimir(data, true);
                    } else {
                        this.cola.unshift({
                            folio: data.folio, mesa: data.mesa,
                            hora: new Date().toLocaleTimeString('es-CO'),
                            ok: null, auto: false, msg: 'Auto-impresión pausada',
                        });
                        if (this.cola.length > 30) this.cola.pop();
                    }
                };

                // Listener Echo solo la primera vez.
                if (!C.echoListo) {
                    C.echoListo = true;
                    echoWhen(e => e.channel('comandas').listen('.ComandaImprimible', (data) => {
                        C.imprimir && C.imprimir(data);
                    }));
                }

                if (typeof qz === 'undefined') {
                    this.estado = 'sin-app';
                    C.conectado = false;
                    return;
                }

                // Configurar seguridad con certificado (idempotente).
                const cert = `-----BEGIN CERTIFICATE-----
MIIDcjCCAlqgAwIBAgIBADANBgkqhkiG9w0BAQ0FADBSMRYwFAYDVQQDDA1UaWVu
ZGFzIEthaHZlMRYwFAYDVQQKDA1UaWVuZGFzIEthaHZlMQswCQYDVQQGEwJDTzET
MBEGA1UECAwKU29tZS1TdGF0ZTAeFw0yNjA2MjYyMTAxNDdaFw0zNjA2MjMyMTAx
NDdaMFIxFjAUBgNVBAMMDVRpZW5kYXMgS2FodmUxFjAUBgNVBAoMDVRpZW5kYXMg
S2FodmUxCzAJBgNVBAYTAkNPMRMwEQYDVQQIDApTb21lLVN0YXRlMIIBIjANBgkq
hkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyfHd9n/NrjE7Wa6WpuDPBo0E/sNwZEQX
NmFbDNE//h1XUQM+jOK+dVgV8LHmmdeaM6JzS1jt/dnNKorV7GsGXDoGcH6ZGWW9
8vfSf0gvkAa5JMJ0u1azico+icPjtXr1urf3BuKPWtQmvFgIZQBbJEvtTprIXAe+
SmY4EPfyxWXIYj5NydmZ8QdQANJaHgwHw6jkrC6by6fSgdmUwgS2GEN7EBlWTMq9
Kv+F8Z7CNeP9SpH37pelSKLB71EcFO4093L/7tqg2e6jqaB4mZZ+YWL2IyPk8sXJ
+a/1KZ0zScLpFB/G8yUBgYWexzkTr5zQpay6SRbKzFMVz3ZlmQTKzQIDAQABo1Mw
UTAdBgNVHQ4EFgQUAUsBk1gUJKIWvMK06r2L8bCAHaEwHwYDVR0jBBgwFoAUAUsB
k1gUJKIWvMK06r2L8bCAHaEwDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0BAQ0F
AAOCAQEApTd8gmdJEMpycGVBajT5mCBG01xSTocyWW4m7NyXbGuYmeXZNRqKFv+T
ookss9M7wWoDq4+6iW8lVybxr1UHLgDV1S4scDL0xSuUBcycaSBzIIvlSxev46UD
Y1j5sqgwdHoNKU3T7Jeeln+pLgPduUK38crkYi+DjuF4kvEE2b46E4GMklChxFkw
iYvTJ1DCQA7gVlN1NIZcjVQYVnZsRaRwIjaZ31kgtRO/IpdqjyyHuZrgWE/3Bt0t
W0VkDMVPYbHd1MP6OvBOQ+O/GW8Fy/uOzK8mZR13uG18DcIjiDvlRIY0mNTmJbW7
J0Ylddlh12lm5SXjdZyvkWMmALKsiw==
-----END CERTIFICATE-----`;
                qz.security.setCertificatePromise((resolve) => resolve(cert));
                qz.security.setSignatureAlgorithm('SHA512');
                qz.security.setSignaturePromise((toSign) => (resolve, reject) => {
                    fetch('/qz-sign?request=' + encodeURIComponent(toSign))
                        .then(r => r.text()).then(resolve).catch(reject);
                });

                if (C.conectado) {
                    // Ya estaba conectado: verificar sin mostrar estado intermedio.
                    try {
                        const activo = typeof qz.websocket.isActive === 'function'
                            ? qz.websocket.isActive() : false;
                        if (!activo) await qz.websocket.connect();
                        this.impresoras = await qz.printers.find();
                        C.impresoras    = this.impresoras.slice();
                        this.estado     = 'conectado';
                    } catch (err) {
                        C.conectado   = false;
                        this.estado   = 'error';
                        this.errorMsg = err.message || String(err);
                    }
                } else {
                    await this.conectar();
                }
            },

            async conectar() {
                const C = window._qz;
                if (typeof qz === 'undefined') { this.estado = 'sin-app'; return; }
                this.estado   = 'conectando';
                this.errorMsg = '';
                try {
                    if (!qz.websocket.isActive()) await qz.websocket.connect();
                    this.impresoras = await qz.printers.find();
                    this.estado     = 'conectado';
                    C.conectado     = true;
                    C.impresoras    = this.impresoras.slice();
                } catch (err) {
                    this.estado   = 'error';
                    C.conectado   = false;
                    this.errorMsg = err.message || String(err);
                }
            },

            guardarImpresora() {
                localStorage.setItem('qz_impresora', this.impresora);
                window._qz.impresora = this.impresora;
            },

            async imprimirManual() {
                if (!this.folioManual.trim()) return;
                this.buscando = true;
                try {
                    const data = await this.$wire.buscarComanda(this.folioManual.trim());
                    if (!data) {
                        alert('No se encontró la comanda: ' + this.folioManual.trim().toUpperCase());
                        return;
                    }
                    await this.imprimir(data, false);
                    this.folioManual = '';
                } finally {
                    this.buscando = false;
                }
            },

            async imprimir(data, esAuto) {
                const entrada = {
                    folio: data.folio, mesa: data.mesa,
                    hora: new Date().toLocaleTimeString('es-CO'),
                    auto: esAuto, ok: null, msg: '',
                };

                if (!this.impresora) {
                    entrada.ok  = false;
                    entrada.msg = 'Sin impresora seleccionada';
                    this.cola.unshift(entrada);
                    if (this.cola.length > 30) this.cola.pop();
                    return;
                }

                try {
                    const config = qz.configs.create(this.impresora);
                    await qz.print(config, this.buildEscPos(data));
                    entrada.ok = true;
                } catch (err) {
                    entrada.ok  = false;
                    entrada.msg = err.message || 'Error desconocido';
                }

                this.cola.unshift(entrada);
                if (this.cola.length > 30) this.cola.pop();
            },

            buildEscPos(data) {
                const ESC = '\x1B', GS = '\x1D', LF = '\n';
                const W = 42;
                const fill = (ch) => ch.repeat(W) + LF;

                // Texto izquierda + derecha alineados en W columnas
                const rAlign = (left, right) => {
                    const gap = W - left.length - right.length;
                    return left + ' '.repeat(Math.max(1, gap)) + right + LF;
                };

                // Formato pesos colombianos: 16000 → $16.000
                const peso = (n) => '$' + String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

                let raw = '';
                raw += ESC + '@';        // Inicializar impresora
                raw += ESC + 't\x00';   // Codepage PC437

                // ── Encabezado ──────────────────────────────
                raw += ESC + 'a\x01';   // Centrar

                raw += ESC + '!\x38';   // Doble tamaño + negrita
                raw += (data.tienda || 'TIENDA') + LF;
                raw += ESC + '!\x00';

                raw += ESC + 'E\x01';   // Negrita
                raw += '*** COMANDA ***' + LF;
                raw += ESC + 'E\x00';

                raw += ESC + '!\x30';   // Doble tamaño
                raw += (data.folio || '') + LF;
                raw += ESC + '!\x00';

                raw += (data.fecha || '') + LF;
                raw += ESC + 'a\x00';   // Izquierda

                // ── Mesa y mesero ────────────────────────────
                raw += fill('=');
                raw += 'Mesa:    ' + (data.mesa   || '-') + LF;
                raw += 'Mesero:  ' + (data.mesero || '-') + LF;

                // ── Items ────────────────────────────────────
                raw += fill('=');
                raw += rAlign('CANT  DESCRIPCION', 'SUBTOTAL');
                raw += fill('-');

                let total = 0;
                data.items.forEach(item => {
                    const sub = item.subtotal ?? (item.cantidad * (item.precio ?? 0));
                    total += sub;
                    const qty    = String(item.cantidad).padStart(2);
                    const nombre = (item.nombre || '').substring(0, 22);
                    raw += ESC + 'E\x01';
                    raw += rAlign('  ' + qty + '  ' + nombre, peso(sub));
                    raw += ESC + 'E\x00';
                    if (item.observacion) raw += '      > ' + item.observacion + LF;
                });

                raw += fill('-');
                raw += ESC + 'E\x01';
                raw += rAlign('  TOTAL', peso(total));
                raw += ESC + 'E\x00';
                raw += fill('=');

                // ── Cliente ──────────────────────────────────
                if (data.cliente) {
                    raw += 'Cliente: ' + data.cliente + LF;
                    if (data.cc)       raw += 'CC:      ' + data.cc       + LF;
                    if (data.telefono) raw += 'Tel:     ' + data.telefono + LF;
                    raw += fill('=');
                }

                // ── Pie ──────────────────────────────────────
                raw += ESC + 'a\x01';   // Centrar
                raw += ESC + 'E\x01';
                raw += 'Gracias por su visita!' + LF;
                raw += ESC + 'E\x00';
                raw += ESC + 'a\x00';

                raw += LF + LF + LF;
                raw += GS + 'V\x41\x00';   // Cortar papel

                return [{ type: 'raw', format: 'plain', data: raw }];
            },
        };
    });
});
