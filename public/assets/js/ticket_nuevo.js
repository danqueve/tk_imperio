(function () {
    'use strict';
    const MAX_IMAGENES = 3;
    const LADO_MAX = 1600;
    const form = document.getElementById('form-nuevo');

    /* ---- Ticket / Tarea ---- */
    function aplicarTipo() {
        const tipo = form.querySelector('input[name="tipo"]:checked').value;
        form.querySelectorAll('[data-solo]').forEach(function (sec) {
            const activo = sec.dataset.solo === tipo;
            sec.classList.toggle('d-none', !activo);
            sec.querySelectorAll('input, textarea, select').forEach(function (el) { el.disabled = !activo; });
        });
    }
    form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', aplicarTipo); });
    aplicarTipo();

    /* ---- Búsqueda por DNI ---- */
    const dni = document.getElementById('dni');
    const cliente = document.getElementById('cliente');
    const sasId = document.getElementById('sas_cliente_id');
    const estado = document.getElementById('dni-estado');
    const lista = document.getElementById('dni-lista');
    let ultimo = '';

    function elegir(c) {
        cliente.value = c.nombre;
        sasId.value = c.id;
        lista.classList.add('d-none');
        estado.textContent = 'Cliente de SAS Imperio seleccionado.';
    }

    async function buscar() {
        const valor = dni.value.replace(/\D+/g, '');
        if (valor === ultimo) { return; }
        ultimo = valor;
        sasId.value = '';
        lista.classList.add('d-none');
        lista.replaceChildren();
        if (valor.length < 6) { estado.textContent = ''; return; }
        estado.textContent = 'Buscando en SAS Imperio…';
        try {
            const r = await fetch(window.TK.urlCliente + '?dni=' + encodeURIComponent(valor), { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.ok) {
                estado.textContent = 'No se pudo consultar SAS. Escribí el nombre a mano.';
            } else if (j.clientes.length === 0) {
                estado.textContent = 'No está en SAS Imperio. Escribí el nombre a mano.';
            } else if (j.clientes.length === 1) {
                elegir(j.clientes[0]);
            } else {
                estado.textContent = 'Hay ' + j.clientes.length + ' clientes con ese DNI. Elegí uno:';
                j.clientes.forEach(function (c) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action';
                    b.textContent = c.nombre;
                    b.addEventListener('click', function () { elegir(c); });
                    lista.appendChild(b);
                });
                lista.classList.remove('d-none');
            }
        } catch (e) {
            estado.textContent = 'No se pudo consultar SAS. Escribí el nombre a mano.';
        }
    }
    dni.addEventListener('blur', buscar);
    cliente.addEventListener('input', function () { sasId.value = ''; });

    /* ---- Imágenes: reducir en el navegador ---- */
    const input = document.getElementById('imagenes');
    const previas = document.getElementById('previas');
    const btn = document.getElementById('btn-enviar');

    function reducir(file) {
        return new Promise(function (resolve) {
            const img = new Image();
            const src = URL.createObjectURL(file);
            img.onload = function () {
                URL.revokeObjectURL(src);
                const k = Math.min(1, LADO_MAX / Math.max(img.width, img.height));
                const c = document.createElement('canvas');
                c.width = Math.round(img.width * k);
                c.height = Math.round(img.height * k);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(function (blob) {
                    if (!blob) { resolve(file); return; }
                    const nombre = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    resolve(new File([blob], nombre, { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { URL.revokeObjectURL(src); resolve(file); };
            img.src = src;
        });
    }

    input.addEventListener('change', async function () {
        let files = Array.from(input.files);
        if (files.length > MAX_IMAGENES) {
            alert('Solo se pueden subir hasta ' + MAX_IMAGENES + ' imágenes. Se usarán las primeras ' + MAX_IMAGENES + '.');
            files = files.slice(0, MAX_IMAGENES);
        }
        btn.disabled = true;
        const reducidas = await Promise.all(files.map(reducir));
        const dt = new DataTransfer();
        reducidas.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
        previas.replaceChildren();
        reducidas.forEach(function (f) {
            const im = document.createElement('img');
            im.src = URL.createObjectURL(f);
            im.className = 'rounded border';
            im.style.cssText = 'height:72px;width:72px;object-fit:cover';
            previas.appendChild(im);
        });
        btn.disabled = false;
    });
}());
