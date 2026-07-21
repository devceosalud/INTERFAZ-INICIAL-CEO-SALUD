const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('preview');
const previewImg = document.getElementById('previewImg');
const estado = document.getElementById('estado');
const resultado = document.getElementById('resultado');
const numeroDetectado = document.querySelector('#appointmentModalCreate #numero_operacion'); // CAMPO OPERCION
const debugTexto = document.getElementById('debugTexto');
const btnCopiar = document.getElementById('btnCopiar');
const btnCopiarTexto = document.getElementById('btnCopiarTexto');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files[0]) procesarArchivo(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) procesarArchivo(fileInput.files[0]);
});

function pareceFechaUOtroRuido(token) {
    return /^\d{1,2}[\/\-]\d{1,2}/.test(token) ||
        /^\d{1,2}:\d{2}/.test(token) ||
        (/^\d{4}$/.test(token) && Number(token) > 1900 && Number(token) < 2100);
}

function extraerNumeroOperacion(rawText) {
    const flat = rawText.replace(/[ \t]+/g, ' ').replace(/\s*\n\s*/g, ' ').trim();
    const etiquetaRegex = /\b(?:n(?:úmero|ro|°)\.?\s*(?:de\s*)?)(operaci[oó]n|transferencia)\s*[:\-]?\s*/gi;

    const candidatos = [];
    let m;
    while ((m = etiquetaRegex.exec(flat)) !== null) {
        const tipoEtiqueta = m[1].toLowerCase().startsWith('operaci') ? 0 : 1;
        const resto = flat.slice(m.index + m[0].length, m.index + m[0].length + 40);
        const tokenMatch = resto.match(/^([A-Z0-9]{4,20})(?:\s+([A-Z0-9]{2,10}))?/i);
        if (!tokenMatch) continue;
        const primerToken = tokenMatch[1];
        if (pareceFechaUOtroRuido(primerToken)) continue;
        const valor = (tokenMatch[1] + (tokenMatch[2] || '')).toUpperCase();
        candidatos.push({ prioridad: tipoEtiqueta, valor });
    }

    if (candidatos.length > 0) {
        candidatos.sort((a, b) => a.prioridad - b.prioridad);
        return candidatos[0].valor;
    }

    const regexesRespaldo = [
        /\d{3}\.\d{3}\.\d{3}\.\d{4}/,
        /\b\d{6,15}\b/,
        /\b[A-Z0-9]{6,20}\b/
    ];
    for (const r of regexesRespaldo) {
        const mm = flat.match(r);
        if (mm) return mm[0];
    }
    return null;
}

async function procesarArchivo(file) {
    resultado.classList.remove('visible');
    estado.classList.remove('error');
    debugTexto.textContent = '—';

    previewImg.src = URL.createObjectURL(file);
    preview.style.display = 'block';
    estado.textContent = 'Leyendo la imagen… puede tardar unos segundos';

    try {
        const { data: { text } } = await Tesseract.recognize(file, 'spa', {
            logger: (info) => {
                if (info.status === 'recognizing text') {
                    estado.textContent = `Leyendo la imagen… ${Math.round(info.progress * 100)}%`;
                }
            }
        });

        debugTexto.textContent = text || '(sin texto detectado)';
        const numero = extraerNumeroOperacion(text);

        if (numero) {
            console.log('NUMERO DETECTADO :', numero);
            
            estado.textContent = '';
            numeroDetectado.value = numero;
            //resultado.classList.add('visible');
            //btnCopiar.classList.remove('copiado');
            //btnCopiarTexto.textContent = 'Copiar';
        } else {
            estado.textContent = 'No se encontró un número de operación en esta imagen. Revisa el texto reconocido abajo.';
            estado.classList.add('error');
        }
    } catch (err) {
        console.error('Error de OCR:', err);
        estado.textContent = 'Ocurrió un error al leer la imagen: ' + (err && err.message ? err.message : err);
        estado.classList.add('error');
    }
}

/*
btnCopiar.addEventListener('click', async () => {
    const valor = numeroDetectado.textContent;
    if (!valor || valor === '—') return;
    try {
        await navigator.clipboard.writeText(valor);
    } catch (e) {
        // Respaldo por si el navegador bloquea el portapapeles (ej. sin HTTPS)
        const area = document.createElement('textarea');
        area.value = valor;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
    }
    btnCopiar.classList.add('copiado');
    btnCopiarTexto.textContent = 'Copiado ✓';
    setTimeout(() => {
        btnCopiar.classList.remove('copiado');
        btnCopiarTexto.textContent = 'Copiar';
    }, 1800);
});*/