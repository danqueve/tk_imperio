(function () {
    'use strict';
    const CFG = window.PANTALLA;
    const $cols = document.getElementById('cols');
    const $act = document.getElementById('actualizado');
    const $sin = document.getElementById('sinconexion');
    const $total = document.getElementById('total');

    let ultimo = null;          // JSON de la última lista dibujada
    let datos = [];
    let vistos = null;          // ids ya mostrados (null = primera carga, sin resaltar)
    let intervalo = 300;
    let proxima = 0;            // epoch ms de la próxima consulta
    let ultimaOk = null;
    let desfase = 0;            // segundos servidor - cliente
    let timer = null;

    const pad = function (n) { return String(n).padStart(2, '0'); };
    const hora = function (d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()); };
    const ahoraSrv = function () { return Date.now() / 1000 + desfase; };

    function hace(ts) {
        const s = Math.max(0, ahoraSrv() - ts);
        if (s < 60) { return 'hace un momento'; }
        if (s < 3600) { return 'hace ' + Math.floor(s / 60) + ' min'; }
        if (s < 86400) { return 'hace ' + Math.floor(s / 3600) + ' h'; }
        return 'hace ' + Math.floor(s / 86400) + ' d';
    }

    function fechaCorta(ts) {
        const d = new Date(ts * 1000);
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + hora(d);
    }

    function iniciales(nombre) {
        const p = String(nombre || '').trim().split(/\s+/).filter(Boolean);
        if (p.length === 0 || nombre === 'Sin asignar') { return '?'; }
        return p.slice(0, 2).map(function (x) { return x.charAt(0).toUpperCase(); }).join('');
    }

    function el(tag, cls, txt) {
        const e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (txt !== undefined) { e.textContent = txt; }
        return e;
    }

    function tarjeta(t, nueva) {
        const c = el('div', 'card' + (t.estado === 'en_proceso' ? ' proceso' : '') + (t.alta ? ' alta' : '') + (nueva ? ' nueva' : ''));
        c.appendChild(el('div', 't', t.titulo));

        const m = el('div', 'm');
        m.appendChild(document.createTextNode((t.tipo === 'ticket' && t.dni ? 'DNI ' + t.dni + ' · ' : '')));
        const edad = el('span', 'edad', hace(t.creado));
        edad.dataset.creado = t.creado;
        m.appendChild(edad);
        if (t.imagenes > 0) { m.appendChild(document.createTextNode(' · ' + t.imagenes + (t.imagenes === 1 ? ' foto' : ' fotos'))); }
        c.appendChild(m);

        if (t.compromiso) {
            const comp = el('div', 'm comp', 'Compromiso: ' + fechaCorta(t.compromiso) + (t.detalle ? ' · ' + t.detalle : ''));
            comp.dataset.compromiso = t.compromiso;
            comp.classList.toggle('venc', t.compromiso < ahoraSrv());
            c.appendChild(comp);
        }

        const tags = el('div', 'tags');
        if (t.tipo === 'tarea') { tags.appendChild(el('span', 'tag tarea', 'TAREA')); }
        if (t.estado === 'en_proceso') { tags.appendChild(el('span', 'tag proceso', 'EN PROCESO')); }
        if (t.alta) { tags.appendChild(el('span', 'tag alta', 'PRIORIDAD ALTA')); }
        if (tags.childNodes.length) { c.appendChild(tags); }
        return c;
    }

    function ajustar(col) {
        const box = col.querySelector('.tarjetas');
        if (window.matchMedia('(max-width: 700px)').matches) { return; }
        const cards = Array.from(box.querySelectorAll('.card'));
        let ocultas = 0;
        const mas = el('div', 'mas');
        const sobra = function () { return box.scrollHeight > box.clientHeight + 1; };
        while (cards.length > 1 && sobra()) {
            cards.pop().remove();
            ocultas++;
        }
        if (ocultas > 0) {
            mas.textContent = '+' + ocultas + ' más';
            box.appendChild(mas);
            while (cards.length > 1 && sobra()) {
                cards.pop().remove();
                ocultas++;
                mas.textContent = '+' + ocultas + ' más';
            }
        }
    }

    function dibujar() {
        $cols.replaceChildren();
        $cols.classList.toggle('muchas', datos.length > 6);

        const total = datos.reduce(function (n, c) { return n + c.tickets.length; }, 0);
        if ($total) { $total.textContent = total === 1 ? '1 en curso' : total + ' en curso'; }

        if (datos.length === 0) {
            const v = el('div', 'vacio');
            v.appendChild(el('b', '', '✓'));
            v.appendChild(document.createTextNode('No hay tickets abiertos'));
            $cols.appendChild(v);
            return;
        }
        const fragment = document.createDocumentFragment();
        datos.forEach(function (c) {
            // Color fijo por usuario (según su id) para que no cambie entre actualizaciones.
            const col = el('section', 'col ' + (c.id === 0 ? 'sin' : 'p' + (c.id % 8)));
            const h = el('h2');
            const nom = el('span', 'nom');
            nom.appendChild(el('span', 'ini', iniciales(c.nombre)));
            nom.appendChild(el('span', '', c.nombre));
            h.appendChild(nom);
            h.appendChild(el('span', 'n', String(c.tickets.length)));
            col.appendChild(h);
            const box = el('div', 'tarjetas');
            c.tickets.forEach(function (t) {
                box.appendChild(tarjeta(t, vistos !== null && !vistos.has(t.id)));
            });
            col.appendChild(box);
            fragment.appendChild(col);
        });
        $cols.appendChild(fragment);
        $cols.querySelectorAll('.col').forEach(ajustar);
    }

    function marcarVistos() {
        vistos = new Set();
        datos.forEach(function (c) { c.tickets.forEach(function (t) { vistos.add(t.id); }); });
    }

    function programar(seg) {
        clearTimeout(timer);
        proxima = Date.now() + seg * 1000;
        timer = setTimeout(cargar, seg * 1000);
    }

    async function cargar() {
        try {
            const r = await fetch(CFG.api, { cache: 'no-store' });
            if (!r.ok) { throw new Error('http ' + r.status); }
            const j = await r.json();
            if (!j.ok) { throw new Error('api'); }

            desfase = j.ahora - Date.now() / 1000;
            intervalo = j.intervalo;
            const firma = JSON.stringify(j.columnas);
            if (firma !== ultimo) {
                datos = j.columnas;
                dibujar();
                ultimo = firma;
            }
            marcarVistos();
            ultimaOk = new Date();
            $sin.hidden = true;
            programar(intervalo);
        } catch (e) {
            // Conserva lo último que mostró y reintenta pronto.
            $sin.hidden = false;
            programar(CFG.reintento);
        }
        pintarEstado();
    }

    function pintarEstado() {
        const resto = Math.max(0, Math.round((proxima - Date.now()) / 1000));
        $act.textContent = (ultimaOk ? 'Actualizado ' + hora(ultimaOk) : 'Sin datos') +
            ' · próxima ' + Math.floor(resto / 60) + ':' + pad(resto % 60);
    }

    function refrescarEdades() {
        document.querySelectorAll('.edad').forEach(function (s) { s.textContent = hace(Number(s.dataset.creado)); });
        document.querySelectorAll('.comp').forEach(function (s) {
            s.classList.toggle('venc', Number(s.dataset.compromiso) < ahoraSrv());
        });
    }

    /* Recarga completa diaria: toma cambios de código y libera memoria. */
    function recargaDiaria() {
        const n = new Date();
        const t = new Date(n.getFullYear(), n.getMonth(), n.getDate(), CFG.recargaHora, 0, 0);
        if (t <= n) { t.setDate(t.getDate() + 1); }
        setTimeout(function () { location.reload(); }, t - n);
    }

    /* Que la TV no se duerma (requiere HTTPS). */
    let wake = null;
    async function mantenerDespierta() {
        try {
            if ('wakeLock' in navigator && document.visibilityState === 'visible') {
                wake = await navigator.wakeLock.request('screen');
            }
        } catch (e) { /* sin permiso o sin HTTPS */ }
    }
    document.addEventListener('visibilitychange', mantenerDespierta);

    document.getElementById('pantalla-completa').addEventListener('click', function () {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        }
    });

    let rz = null;
    window.addEventListener('resize', function () {
        clearTimeout(rz);
        rz = setTimeout(dibujar, 250);
    });

    setInterval(pintarEstado, 1000);
    setInterval(refrescarEdades, 30000);
    recargaDiaria();
    mantenerDespierta();
    cargar();
}());
