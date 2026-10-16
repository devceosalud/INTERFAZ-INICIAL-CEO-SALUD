(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.AppointmentReceiptOCR = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    const MAX_BYTES = 8 * 1024 * 1024;
    function unique(values) { return [...new Set(values.filter(Boolean))]; }
    // Canonical PEN amount. Do not reinterpret bare long identifiers as money.
    function amount(value) {
        const raw = String(value || '').replace(/\s/g, '');
        if (!/^\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?$|^\d{1,8}(?:[.,]\d{2})?$/.test(raw)) return null;
        const comma = raw.lastIndexOf(','), dot = raw.lastIndexOf('.');
        const decimalAt = Math.max(comma, dot);
        const decimals = decimalAt >= 0 && raw.length - decimalAt - 1 === 2;
        const whole = (decimals ? raw.slice(0, decimalAt) : raw).replace(/[.,]/g, '');
        const cents = Number(whole) * 100 + (decimals ? Number(raw.slice(decimalAt + 1)) : 0);
        return Number.isSafeInteger(cents) && cents > 0 && cents <= 9999999900 ? (cents / 100).toFixed(2) : null;
    }
    function parse(text) {
        const normalized = String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\r/g, '');
        const lines = normalized.split('\n').map(line=>line.trim()).filter(Boolean);
        const money = [];
        const namedAmount = /\b(monto|importe|total\s+(?:pagado|enviado|recibido)|yapeaste|pagaste|enviaste|transferiste|recibiste)\b/i;
        const excluded = /\b(saldo|comision|disponible|telefono|celular|numero de cuenta|nro\.?\s*de\s*operacion)\b/i;
        // Inside a currency amount only, OCR commonly confuses S/50 with S/SO.
        const ocrAmount = raw => amount(String(raw).replace(/[Oo]/g,'0').replace(/[Ss]/g,'5').replace(/[Il|]/g,'1'));
        const currency = /(?:\bS\s*[\/|]\s*\.?\s*|\bPEN\s*:?\s*)([0-9OoSsIl|]{1,8}(?:[.,][0-9OoSsIl|]{2,3})*)/gi;
        for(let index=0;index<lines.length;index++){
            const line = lines[index];
            if(excluded.test(line)) continue;
            const priority = namedAmount.test(line) ? 3 : 2;
            let match;
            while((match=currency.exec(line))!==null){
                const value=ocrAmount(match[1]);
                if(value)money.push({value,priority});
            }
            currency.lastIndex=0;
            // Short linebreaks in tiny Yape screenshots: "S/" on one line, "50" on the next.
            if(/(?:\bS\s*[/|]\s*\.?|\bPEN)\s*$/i.test(line)&&index+1<lines.length) {
                const next=lines[index+1].match(/^([0-9OoSsIl|]{1,7}(?:[.,][0-9OoSsIl|]{2})?)(?!\d)/);
                if(next){const value=ocrAmount(next[1]);if(value)money.push({value,priority:3});}
            }
            // OCR sometimes drops "S/" entirely but leaves a standalone value below "Yapeaste".
            if(namedAmount.test(line) && index+1<lines.length && !excluded.test(lines[index+1])) {
                const next=lines[index+1].match(/^([0-9]{1,5}(?:[.,][0-9]{2})?)$/);
                if(next){const value=amount(next[1]);if(value)money.push({value,priority:1});}
            }
            if(namedAmount.test(line) && !/(?:\bS\s*[/|]|\bPEN)/i.test(line)){
                const label=line.match(/(?:monto|importe|total\s+(?:pagado|enviado)|yapeaste|pagaste|enviaste)\s*(?::|=)?\s*(\d{1,6}(?:[.,]\d{2})?)\b/i);
                if(label){const value=amount(label[1]);if(value)money.push({value,priority:2});}
            }
        }
        const rank=Math.max(0,...money.map(item=>item.priority));
        const amounts=unique(money.filter(item=>item.priority===rank).map(item=>item.value));
        const operations=[];
        const operationPattern=/\b(?:n(?:umer[o0])?|nro|num|codigo|cod|referencia|ref)?\s*(?:[.°º#]\s*)?(?:de(?:\s+la)?\s+)?(?:operacion|transaccion|transferencia|referencia)\s*(?:n(?:umero|ro)?[.°º#]?\s*)?[:#=-]?\s*([0-9][0-9\s-]{4,24}[0-9])(?!\d)/gi;
        for(const line of lines){
            let m;
            while((m=operationPattern.exec(line))!==null){
                const digits=m[1].replace(/[\s-]/g,'');
                if(/^\d{6,20}$/.test(digits))operations.push(digits);
            }
            operationPattern.lastIndex=0;
        }
        const uniqueOperations=unique(operations);
        const banks=unique([...normalized.matchAll(/\b(YAPE|PLIN|BCP|BBVA|INTERBANK)\b/gi)].map(m=>m[1].toUpperCase()));
        const bank=banks.includes('YAPE')?'YAPE':banks.includes('PLIN')?'PLIN':banks.length===1?banks[0]:null;
        const dates=unique([...normalized.matchAll(/\b([0-3][0-9])[/-]([01][0-9])[/-](20[0-9]{2})\b/g)].map(m=>{
            const iso=m[3]+'-'+m[2]+'-'+m[1],d=new Date(iso+'T12:00:00Z');
            return Number.isNaN(d.getTime())||d.toISOString().slice(0,10)!==iso?null:iso;
        }));
        const times=unique([...normalized.matchAll(/\b([01][0-9]|2[0-3]):([0-5][0-9])(?:[:][0-5][0-9])?\b/g)].map(m=>m[1]+':'+m[2]));
        return {
            amount:amounts.length===1?amounts[0]:null,
            operation:uniqueOperations.length===1?uniqueOperations[0]:null,
            bank,date:dates.length===1?dates[0]:null,time:times.length===1?times[0]:null,
            needs_review:true,ambiguous:amounts.length>1||uniqueOperations.length>1,
        };
    }
    function createEngine(options) {
        const opts = options || {};
        let generation = 0;
        const active = new Set();
        const base = new URL(opts.basePath || '/vendor/appointment-ocr/', opts.origin || location.origin);
        const origin = opts.origin || location.origin;
        if (base.origin !== origin) throw new Error('El motor OCR debe alojarse en el mismo origen.');
        const workerFactory = opts.workerFactory || ((...args) => Tesseract.createWorker(...args));
        async function cancel() {
            generation++;
            const workers = [...active]; active.clear();
            await Promise.allSettled(workers.map(w => w.terminate()));
        }
        async function recognize(file, onProgress) {
            await cancel();
            const current = generation;
            if (!file || !['image/png','image/jpeg'].includes(file.type) || file.size < 1 || file.size > MAX_BYTES) {
                throw new Error('OCR admite imágenes JPG o PNG de hasta 8 MB. Puedes registrar los datos manualmente.');
            }
            let worker, timer;
            let timedOut = false;
            const timeout = new Promise((_, reject) => { timer = setTimeout(() => {
                timedOut = true; if (worker) void worker.terminate();
                reject(new Error('El OCR excedió el tiempo disponible. Revisa o completa los datos manualmente.'));
            }, opts.timeoutMs || 30000); });
            const work = (async () => {
                worker = await workerFactory('spa', 1, {
                    workerPath: new URL('worker.min.js',base).href, corePath: new URL('core/',base).href,
                    langPath: new URL('lang/',base).href, workerBlobURL: false, cacheMethod: 'write',
                    logger: m => { if (current === generation && onProgress) onProgress(Math.max(0, Math.min(1, Number(m.progress) || 0))); },
                    errorHandler: () => {}
                });
                if (current !== generation || timedOut) { await worker.terminate(); throw new Error('Lectura descartada.'); }
                active.add(worker);
                // Enhance tiny receipt screenshots locally; never upload or retain image pixels.
                let input = file;
                if (typeof createImageBitmap === 'function' && typeof document !== 'undefined') {
                    try {
                        const bitmap=await createImageBitmap(file);
                        const scale=Math.min(3, Math.max(1, Math.ceil(1200 / bitmap.width)));
                        if(scale>1 && bitmap.width*scale<=2400 && bitmap.height*scale<=5000){
                            const canvas=document.createElement('canvas');
                            canvas.width=bitmap.width*scale;canvas.height=bitmap.height*scale;
                            const context=canvas.getContext('2d');
                            if(context){context.imageSmoothingEnabled=true;context.drawImage(bitmap,0,0,canvas.width,canvas.height);input=canvas;}
                        }
                        bitmap.close();
                    }catch(_){ input=file; }
                }
                if(current !== generation || timedOut) throw new Error('Lectura descartada.');
                const result = await worker.recognize(input);
                if (current !== generation || timedOut) throw new Error('Lectura descartada.');
                return { ...parse(result.data.text), confidence: Number(result.data.confidence) || 0 };
            })();
            try { return await Promise.race([work, timeout]); }
            catch (error) {
                if (current !== generation) throw new Error('Lectura descartada.');
                if (timedOut) throw new Error('El OCR excedió el tiempo disponible. Completa los datos manualmente.');
                throw new Error('No se pudo leer la imagen. Revisa o completa los datos manualmente.');
            }
            finally { clearTimeout(timer); if (worker) { active.delete(worker); await worker.terminate().catch(() => {}); } }
        }
        return { recognize, cancel };
    }
    return { parse, amount, createEngine, MAX_BYTES };
}));
