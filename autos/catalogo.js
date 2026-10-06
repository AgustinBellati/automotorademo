// Catálogo público de autos: lee los datos que inyecta index.php y renderiza listado y ficha.
(function () {
  'use strict'
  const DATOS = JSON.parse(document.getElementById('datos').textContent)
  const CFG = JSON.parse(document.getElementById('config').textContent)
  const C = DATOS.cuenta
  const AUTOS = DATOS.vehiculos
  const $ = (s) => document.querySelector(s)
  const app = $('#app')

  const fmtPrecio = (n, moneda) => (moneda === 'UYU' ? '$ ' : 'U$S ') + Math.round(n).toLocaleString('es-UY')
  const fmtKm = (n) => n.toLocaleString('es-UY') + ' km'
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c])
  const titulo = (a) => `${a.marca} ${a.modelo} ${a.anio}`
  const urlAuto = (a) => (CFG.bonitas ? CFG.base + a.ref : CFG.base + '&r=' + a.ref)
  const minEntrega = (p) => Math.round((p * C.entrega_min_pct) / 100 / 100) * 100
  const tipos = { sedan: 'sedán', hatch: 'hatch', suv: 'SUV', pickup: 'pickup', otro: '' }
  let filtros = { q: '', marca: '', max: '' }

  function cuota(precio, entrega, meses) {
    const P = Math.max(precio - entrega, 0)
    const r = C.tasa_anual / 100 / 12
    return r === 0 ? P / meses : (P * r) / (1 - Math.pow(1 + r, -meses))
  }

  function evento(tipo, ref) {
    const body = JSON.stringify({ slug: C.slug, ref: ref || null, tipo })
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(CFG.evento, new Blob([body], { type: 'application/json' }))) return
    } catch (e) {}
    fetch(CFG.evento, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {})
  }

  function toast(t) {
    const el = $('#toast')
    el.textContent = t
    el.hidden = false
    clearTimeout(toast.t)
    toast.t = setTimeout(() => (el.hidden = true), 2400)
  }

  function carSVG(tipo) {
    const bodies = {
      sedan: 'M14 62 Q16 48 34 46 L62 44 L84 28 Q92 23 104 23 L146 23 Q158 23 168 33 L182 44 L214 48 Q228 50 228 62 L228 70 L14 70 Z',
      hatch: 'M16 62 Q18 48 36 46 L60 44 L80 27 Q88 22 100 22 L156 22 Q168 22 176 32 L196 50 Q212 52 214 62 L214 70 L16 70 Z',
      suv: 'M14 60 Q14 44 32 42 L56 40 L74 20 Q80 15 92 15 L170 15 Q182 15 190 24 L206 42 Q224 44 226 58 L226 72 L14 72 Z',
      pickup: 'M10 60 Q10 44 28 42 L58 40 L76 20 Q82 15 94 15 L134 15 Q142 15 142 24 L142 40 L224 40 Q232 40 232 50 L232 72 L10 72 Z',
    }
    const t = bodies[tipo] ? tipo : 'sedan'
    const w = t === 'pickup' ? [52, 196] : t === 'hatch' ? [52, 178] : [52, 188]
    return `<svg viewBox="0 0 240 92" aria-hidden="true"><ellipse cx="120" cy="84" rx="108" ry="5" fill="var(--car-shadow)"/>
      <path d="${bodies[t]}" fill="var(--line)" stroke="var(--muted)" stroke-width="1.2"/>
      ${w.map((x) => `<circle cx="${x}" cy="72" r="13" fill="var(--ink)"/><circle cx="${x}" cy="72" r="6" fill="var(--muted)"/>`).join('')}</svg>`
  }

  const portada = (a) => (a.fotos.length ? `<img src="${esc(a.fotos[0].url_800)}" alt="${esc(titulo(a))}" loading="lazy">` : carSVG(a.tipo))
  const pillReservado = (a) => (a.estado === 'reservado' ? '<span class="pill warn">Reservado</span>' : '')

  /* ---------- Listado ---------- */
  function renderCatalogo() {
    document.title = `${C.nombre} · Autos usados`
    if (!AUTOS.length) {
      app.innerHTML = `<h1 style="font-size:32px">Autos usados en stock</h1><p class="desc">Por ahora no hay autos publicados. Escribinos por WhatsApp para consultar.</p>${contacto()}`
      return
    }
    const marcas = [...new Set(AUTOS.map((a) => a.marca))].sort()
    const usd = AUTOS.every((a) => a.moneda === 'USD')
    app.innerHTML = `
      <h1 style="font-size:32px">Autos usados en stock</h1>
      <div class="filters">
        <input id="q" type="search" placeholder="Buscar modelo, ej. Corolla" value="${esc(filtros.q)}" aria-label="Buscar">
        <select id="marca" aria-label="Marca"><option value="">Todas las marcas</option>${marcas.map((m) => `<option ${m === filtros.marca ? 'selected' : ''}>${esc(m)}</option>`).join('')}</select>
        ${usd ? `<select id="max" aria-label="Precio máximo"><option value="">Cualquier precio</option>${[15000, 20000, 25000, 30000, 40000].map((v) => `<option value="${v}" ${String(v) === filtros.max ? 'selected' : ''}>Hasta ${fmtPrecio(v, 'USD')}</option>`).join('')}</select>` : ''}
      </div>
      <div class="count" id="count"></div>
      <div class="grid" id="grid" style="margin-top:10px"></div>
      ${contacto()}`
    const maxPlazo = Math.max(...C.plazos)
    const draw = () => {
      const q = filtros.q.toLowerCase()
      const list = AUTOS.filter((a) => !q || `${titulo(a)} ${a.version}`.toLowerCase().includes(q))
        .filter((a) => !filtros.marca || a.marca === filtros.marca)
        .filter((a) => !filtros.max || a.precio <= +filtros.max)
      $('#count').textContent = `${list.length} ${list.length === 1 ? 'auto' : 'autos'}`
      $('#grid').innerHTML =
        list.map((a) => `
        <a class="card" href="${esc(urlAuto(a))}" data-ref="${esc(a.ref)}" style="color:inherit;text-decoration:none">
          <div class="pic">${portada(a)}<span class="sticker num">${fmtPrecio(a.precio, a.moneda)}</span>${pillReservado(a)}</div>
          <div class="card-body">
            <div class="card-title">${esc(a.marca)} ${esc(a.modelo)}</div>
            <div class="specs num">${a.anio} · ${fmtKm(a.km)}${a.caja ? ' · ' + esc(a.caja) : ''}</div>
            <div class="cuota-hint num">Desde ${fmtPrecio(cuota(a.precio, minEntrega(a.precio), maxPlazo), a.moneda)}/mes</div>
          </div>
        </a>`).join('') || '<p class="desc">No hay autos con esos filtros.</p>'
      document.querySelectorAll('.card').forEach((c) => (c.onclick = (e) => {
        if (e.metaKey || e.ctrlKey) return
        e.preventDefault()
        navegar(c.dataset.ref)
      }))
    }
    $('#q').oninput = (e) => { filtros.q = e.target.value; draw() }
    $('#marca').onchange = (e) => { filtros.marca = e.target.value; draw() }
    if ($('#max')) $('#max').onchange = (e) => { filtros.max = e.target.value; draw() }
    draw()
  }

  function contacto() {
    return `<div class="contact"><b>${esc(C.nombre)}</b>${C.direccion ? `<span>${esc(C.direccion)}</span>` : ''}${C.whatsapp ? `<span>WhatsApp: <span class="num">+${esc(C.whatsapp)}</span></span>` : ''}</div>`
  }

  /* ---------- Ficha ---------- */
  function renderFicha(a) {
    document.title = `${titulo(a)} · ${fmtPrecio(a.precio, a.moneda)}`
    const minE = minEntrega(a.precio)
    const link = CFG.origen + urlAuto(a)
    const texto = `Hola ${C.nombre}, me interesa el ${titulo(a)} (ref ${a.ref}) que vi en su catálogo.`
    const fotos = a.fotos.length ? a.fotos.map((f, i) => `<img src="${esc(f.url_1600)}" alt="${esc(titulo(a))}, foto ${i + 1}" ${i ? 'loading="lazy"' : ''}>`).join('') : `<div class="pic">${carSVG(a.tipo)}</div>`
    const specs = [['Año', a.anio], ['Kilómetros', fmtKm(a.km)], ['Combustible', a.combustible], ['Caja', a.caja], ['Versión', a.version], ['Referencia', a.ref]].filter(([, v]) => v !== '' && v != null)
    const plazoDef = C.plazos.includes(36) ? 36 : C.plazos[Math.floor(C.plazos.length / 2)]
    app.innerHTML = `
      <a class="back" id="back" href="${esc(CFG.base)}" style="display:inline-block;text-decoration:none">← Volver al catálogo</a>
      <div class="ficha">
        <div>
          <div class="gal">
            <div class="gal-track" id="track">${fotos}</div>
            <span class="sticker num">${fmtPrecio(a.precio, a.moneda)}</span>${pillReservado(a)}
            ${a.fotos.length > 1 ? `<span class="gal-count num" id="galCount">1 / ${a.fotos.length}</span>` : ''}
          </div>
          <div class="spec-grid num">${specs.map(([k, v]) => `<div><small>${k}</small><b>${esc(v)}</b></div>`).join('')}</div>
          ${a.descripcion ? `<p class="desc">${esc(a.descripcion)}</p>` : ''}
        </div>
        <div style="display:grid;gap:14px">
          <div>
            <div class="fine">${esc(C.nombre)}</div>
            <h1>${esc(a.marca)} ${esc(a.modelo)} ${a.anio}</h1>
            ${a.version ? `<div class="specs">${esc(a.version)}</div>` : ''}
            <div class="big-price num">${fmtPrecio(a.precio, a.moneda)}</div>
          </div>
          <div class="box">
            <h3>Calculá tu cuota</h3>
            <div class="field">
              <label for="ent">Entrega: <b class="num" id="entV"></b></label>
              <input type="range" id="ent" min="${minE}" max="${a.precio}" step="100" value="${minE}">
            </div>
            <div class="field">
              <label for="plazo">Plazo</label>
              <select id="plazo">${C.plazos.map((p) => `<option value="${p}" ${p === plazoDef ? 'selected' : ''}>${p} cuotas</option>`).join('')}</select>
            </div>
            <div class="cuota"><span>Cuota mensual aprox.</span><b class="num" id="cuotaV"></b></div>
            <div class="fine">Orientativo, tasa ${C.tasa_anual}% anual${a.moneda === 'USD' ? ' en dólares' : ''}. Sujeto a aprobación.</div>
          </div>
          ${C.whatsapp ? `<a class="btn wa" id="wa" href="https://wa.me/${esc(C.whatsapp)}?text=${encodeURIComponent(texto)}" target="_blank" rel="noopener">Consultar por WhatsApp</a>` : ''}
          <button class="btn ghost" id="copy">Copiar link de este auto</button>
        </div>
      </div>
      ${contacto()}`
    const upd = () => {
      const e = +$('#ent').value
      $('#entV').textContent = fmtPrecio(e, a.moneda)
      $('#cuotaV').textContent = fmtPrecio(cuota(a.precio, e, +$('#plazo').value), a.moneda)
    }
    $('#ent').oninput = upd
    $('#plazo').onchange = upd
    upd()
    const track = $('#track')
    if ($('#galCount')) track.onscroll = () => { $('#galCount').textContent = `${Math.round(track.scrollLeft / track.clientWidth) + 1} / ${a.fotos.length}` }
    $('#back').onclick = (e) => { e.preventDefault(); navegar(null) }
    if ($('#wa')) $('#wa').onclick = () => evento('click_whatsapp', a.ref)
    $('#copy').onclick = () => {
      evento('copiar_link', a.ref)
      const ok = () => toast('Link copiado')
      if (navigator.clipboard) navigator.clipboard.writeText(link).then(ok).catch(() => prompt('Copiá el link', link))
      else prompt('Copiá el link', link)
    }
  }

  /* ---------- Navegación ---------- */
  function refActual() {
    if (CFG.bonitas) {
      const resto = location.pathname.slice(CFG.base.length).replace(/\/$/, '')
      return resto || null
    }
    return new URLSearchParams(location.search).get('r')
  }

  function render(registrar) {
    const ref = refActual()
    const a = ref && AUTOS.find((x) => x.ref === ref)
    if (a) {
      renderFicha(a)
      if (registrar) evento('visita_auto', a.ref)
    } else {
      renderCatalogo()
      if (registrar) evento('visita_catalogo')
    }
  }

  function navegar(ref) {
    const a = ref && AUTOS.find((x) => x.ref === ref)
    history.pushState(null, '', a ? urlAuto(a) : CFG.base)
    render(true)
    window.scrollTo(0, 0)
  }

  window.addEventListener('popstate', () => render(false))
  render(true)
})()
