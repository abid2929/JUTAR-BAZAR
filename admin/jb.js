/* JUTAR BAZAR BD: shoe-shop parts of the shared admin (product form, categories, banners, homepage, customers, AI).
   Loaded after admin.js and admin2.js; it replaces their pet-shop pages with shoe-shop ones. */
"use strict";
const SHOP = "JUTAR BAZAR BD";
const GENDER = { men: "Men", women: "Women", kids: "Kids", unisex: "Men & Women" };
const SHOE_COLORS = { Black: "#111", White: "#fff", Brown: "#7a4a24", Tan: "#c8915a", Grey: "#888", Navy: "#1e2a5a", Blue: "#2563eb", Red: "#e23b3b", Maroon: "#7f1d1d", Green: "#16a34a", Beige: "#e8d8b8", Pink: "#ec4899", Cream: "#f3ead3", Olive: "#6b7a2e" };
Object.assign(QUICK_COLORS, SHOE_COLORS);
const THEMES = { purple: ["#5b21b6", "#a855f7"], teal: ["#0f766e", "#2dd4bf"], red: ["#9f0f17", "#ef4444"], navy: ["#0b1f4d", "#2563eb"], green: ["#14532d", "#22c55e"], maroon: ["#4c0519", "#be123c"], pink: ["#9d174d", "#f472b6"], orange: ["#9a3412", "#fb923c"], black: ["#0a0a0a", "#3f3f46"], sand: ["#78350f", "#d6a75c"] };
const grad = t => { const c = THEMES[t] || THEMES.red; return `linear-gradient(135deg,${c[0]},${c[1]})` };

/* products save: the server gives back the saved list with the real product ids */
async function save(c) {
  const j = await api("put", { data: DB[c] }, "&c=" + c);
  if (j.error) { toast(j.error); return false }
  if (c === "products" && j.data) DB.products = j.data;
  return true;
}

/* demo mode extras: own browser storage name so it never mixes with the other shop demos */
Mock.key = k => "jbz_" + k;
const _mockCall = Mock.call.bind(Mock);
Mock.call = async function (a, b, q, form) {
  if (a === "caption") { await new Promise(r => setTimeout(r, 300)); return demoCaption(b) }
  if (a === "accounts") return { data: [] };
  if (a === "account_op") return { ok: 1 };
  return _mockCall(a, b, q, form);
};
function demoCaption(b) {
  const n = b.n || "New shoe", sz = b.sz ? b.sz : "39-44", cl = b.cl || "", price = b.p ? "৳" + b.p : "";
  return { ok: 1, short: `${n}: light, comfortable and made for everyday style.`, description: `${n}: a pair you will want to wear every day.\n\n✔ Soft cushioned insole\n✔ Light, flexible sole\n✔ Easy to match with any outfit\n${cl ? "✔ Color: " + cl + "\n" : ""}✔ Sizes: ${sz}\n\nCash on delivery: pay when it reaches you.`,
    short_bn: `${n}: হালকা, আরামদায়ক আর প্রতিদিনের স্টাইলের জন্য দারুণ।`, description_bn: `${n}: প্রতিদিন পরার মতো একজোড়া জুতা।\n\n✔ নরম কুশন ইনসোল\n✔ হালকা আর নমনীয় সোল\n✔ যেকোনো পোশাকের সাথে মানায়\n✔ সাইজ: ${sz}\n\nক্যাশ অন ডেলিভারি: হাতে পেয়ে টাকা দিন।`,
    fb_post: `👟 নতুন এসেছে: ${n}!\n\n✨ হালকা আর আরামদায়ক\n✨ প্রতিদিনের স্টাইল\n📏 সাইজ: ${sz}\n${price ? "💰 দাম: " + price + "\n" : ""}🚚 সারা দেশে ক্যাশ অন ডেলিভারি\n📩 অর্ডার করতে ইনবক্স করুন\n\n#JutarBazarBD #Shoes #Sneakers #Dhaka`,
    fb_post_en: `👟 Just in: ${n}!\n\n✨ Light and comfortable\n✨ Everyday style\n📏 Sizes: ${sz}\n${price ? "💰 Price: " + price + "\n" : ""}🚚 Cash on delivery all over Bangladesh\n\n#JutarBazarBD #Shoes #Sneakers`, tags: "shoes, sneakers, running, dhaka", source: "template" };
}

/* ---------- product form (shoes) ---------- */
async function ProductEdit(V, id) {
  await load("products", true); await load("categories"); await load("collections");
  const isNew = !id || id === "new", f = isNew ? null : findProd(id); if (!isNew && !f) { V.innerHTML = '<div class="empty">Product not found.</div>'; return }
  const x = f ? JSON.parse(JSON.stringify(f.x)) : { id: newId(), n: "", b: "", st: 10, nw: 1, gx: "men" }; const k0 = f ? f.k : (cats()[0] || {}).k;
  title(isNew ? "Add new product" : "Edit product");
  let colors = (x.cl || "").split(",").map(s => s.trim()).filter(Boolean);
  let gal = [{ s: x.i || x.r || "", c: x.ic || "" }].concat((x.g || []).map(g => ({ s: g.s, c: g.c || "" })));
  const inCols = (DB.collections || []).filter(c => (c.ids || []).includes(x.id)).map(c => c.slug);
  const SZ = ["36", "37", "38", "39", "40", "41", "42", "43", "44", "45", "46"], KSZ = ["26", "27", "28", "29", "30", "31", "32", "33", "34", "35"];
  V.innerHTML = head(isNew ? "Add new product" : "Edit product", "Details, photos, sizes, colours and price", `${!isNew && x.slug ? `<a class="btn w" href="../p/${esc(x.slug)}" target="_blank">${ic("eye")} View in shop</a>` : ""}<a class="btn w" href="#products">← All products</a>`) + `<form class="card card-b form" id="pf" autocomplete="off">
   <div class="row r31"><div class="fld"><label>Product title *</label><input name="n" required maxlength="160" value="${esc(x.n)}" placeholder="e.g. AeroFlex Running Shoe"></div><div class="fld"><label>Brand</label><input name="b" maxlength="60" value="${esc(x.b)}" placeholder="e.g. Jutar Bazar"></div></div>
   <div class="box"><div class="lbl" style="margin-bottom:8px">Where should this product show? *</div><div class="row r3"><div class="fld"><label>Category</label><select name="k">${cats().map(c => `<option value="${c.k}" ${c.k === k0 ? "selected" : ""}>${esc(c.name)}${c.en ? " · " + esc(c.en) : ""}</option>`).join("")}</select></div>
    <div class="fld"><label>For whom</label><select name="gx">${Object.keys(GENDER).map(g => `<option value="${g}" ${g === (x.gx || "men") ? "selected" : ""}>${GENDER[g]}</option>`).join("")}</select><span class="hint">Shows on the Men / Women / Kids pages.</span></div>
    <div class="fld"><label>Catalog collections <i>(optional)</i></label><div class="chips">${(DB.collections || []).map(c => `<label class="chip"><input type="checkbox" name="col" value="${esc(c.slug)}" ${inCols.includes(c.slug) ? "checked" : ""} style="width:14px;height:14px"> ${esc(c.name)}</label>`).join("") || '<span class="hint">No collections yet. Make them in Catalog Collections.</span>'}</div></div></div></div>
   <div class="row r4"><div class="fld"><label>Regular price (৳) *</label><input name="reg" type="number" min="0" value="${x.o || x.p || ""}" placeholder="2200"></div><div class="fld"><label>Sale price (৳) <i>(optional)</i></label><input name="sale" type="number" min="0" value="${x.o ? x.p : ""}" placeholder="e.g. 1950 if discounted"></div><div class="fld"><label>Stock (pairs)</label><input name="st" type="number" min="0" value="${x.st ?? 10}"></div><div class="fld"><label>Label under name <i>(optional)</i></label><input name="t" maxlength="40" value="${esc(x.t || "")}" placeholder="e.g. Premium leather"></div></div>
   <div class="box row r2" style="background:#fff"><div class="fld"><label>Available sizes</label><input name="sz" id="szIn" value="${esc(x.sz || "")}" placeholder="e.g. 39,40,41,42,43"><div class="chips" style="margin-top:4px"><span class="hint">Tap to add or remove:</span>${SZ.map(s => `<button type="button" class="chip sz" data-s="${s}" onclick="togSize('${s}')">${s}</button>`).join("")}</div><div class="chips"><span class="hint">Kids:</span>${KSZ.map(s => `<button type="button" class="chip sz" data-s="${s}" onclick="togSize('${s}')">${s}</button>`).join("")}<button type="button" class="chip" onclick="setSizes(['39','40','41','42','43','44'])">39-44</button></div><span class="hint">Customers must pick one of these sizes when ordering.</span></div>
    <div class="fld"><label>Colours</label><div class="chips" id="clist"></div><div class="chips" style="margin-top:4px"><span class="hint">Quick add:</span>${Object.keys(SHOE_COLORS).map(c => `<button type="button" class="chip" onclick="addColor('${c}')"><i style="background:${SHOE_COLORS[c]}"></i>+ ${c}</button>`).join("")}</div><div class="filters" style="margin-top:6px"><input id="cnew" placeholder="Other colour" style="min-width:0;flex:1"><button type="button" class="btn s" onclick="addColor($('#cnew').value)">+ Add</button></div></div></div>
   <div class="box"><div class="ph" style="margin:0 0 10px"><div><h2 style="font-size:15px">Product images & colour gallery</h2><p>First image is the main photo. Phone camera photos are fine: the site makes them small and turns them the right way up. Tag a colour so the right photo shows when a customer picks that colour.</p></div><span class="tag o">Image 1 required</span></div><div id="glist" class="grid" style="gap:12px"></div><button type="button" class="add-more" style="margin-top:12px" onclick="addGal()">+ Add more image</button></div>
   <div class="aibox"><div><b>✦ AI product copywriter</b><small>Writes English + Bangla description, Facebook post and tags from the title, photo, sizes and price. Works without a key; with a free Gemini key it also looks at the photo.</small></div><div class="filters"><select id="aiTone" style="width:auto"><option value="premium">Premium</option><option value="offer">Offer / sale</option><option value="festive">Eid / festive</option><option value="casual">Friendly</option><option value="simple">Simple</option></select><button type="button" class="btn vi s" onclick="aiWrite(this)">✦ Write with AI</button></div></div>
   <div class="row r2"><div class="fld"><label>Short description (English)</label><textarea name="sd" maxlength="600" style="min-height:70px">${esc(x.sd || "")}</textarea></div><div class="fld"><label>Short description (Bangla)</label><textarea name="sdb" maxlength="600" style="min-height:70px">${esc(x.sdb || "")}</textarea></div></div>
   <div class="row r2"><div class="fld"><label>Full description (English)</label><textarea name="d" maxlength="6000" style="min-height:160px">${esc(x.d || "")}</textarea></div><div class="fld"><label>Full description (Bangla)</label><textarea name="db" maxlength="6000" style="min-height:160px">${esc(x.db || "")}</textarea></div></div>
   <span class="hint" style="margin-top:-8px">Leave the descriptions empty and they are written automatically when you save.</span>
   <div class="row r2"><div class="fld"><label>Facebook post (Bangla) <button type="button" class="btn w s" style="padding:2px 10px;margin-left:6px" onclick="copyTxt('fb')">Copy</button></label><textarea name="fb" maxlength="4000" style="min-height:150px">${esc(x.fb || "")}</textarea></div><div class="fld"><label>Facebook post (English) <button type="button" class="btn w s" style="padding:2px 10px;margin-left:6px" onclick="copyTxt('fbe')">Copy</button></label><textarea name="fbe" maxlength="4000" style="min-height:150px">${esc(x.fbe || "")}</textarea></div></div>
   <div class="fld"><label>Search tags <i>(comma separated)</i></label><input name="tg" maxlength="300" value="${esc(x.tg || "")}" placeholder="running, sports, mesh"></div>
   <div class="chk"><label><input type="checkbox" name="ft" ${x.ft ? "checked" : ""}> Featured</label><label><input type="checkbox" name="fs" ${x.fs ? "checked" : ""}> Flash sale offer</label><label><input type="checkbox" name="nw" ${x.nw ? "checked" : ""}> New arrival</label><label><input type="checkbox" name="bs" ${x.bs ? "checked" : ""}> Best seller</label><label><input type="checkbox" name="off" ${x.off ? "checked" : ""}> Hide from website</label></div>
   <div class="filters"><button class="btn">${ic("check")} ${isNew ? "Publish product" : "Save product"}</button><a class="btn w" href="#products">← Cancel and go back</a></div></form>`;
  const szSync = () => { const cur = $("#szIn").value.split(",").map(s => s.trim()); $$(".chip.sz").forEach(b => b.classList.toggle("on", cur.includes(b.dataset.s))) };
  window.togSize = s => { let cur = $("#szIn").value.split(",").map(v => v.trim()).filter(Boolean); cur = cur.includes(s) ? cur.filter(v => v !== s) : [...cur, s]; cur.sort((a, b) => a - b); $("#szIn").value = cur.join(","); szSync() };
  window.setSizes = a => { $("#szIn").value = a.join(","); szSync() };
  $("#szIn").oninput = szSync; szSync();
  window.copyTxt = n => { const t = $(`#pf [name=${n}]`).value; if (!t) return toast("Nothing to copy yet"); (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(() => toast("Copied ✓"), () => { $(`#pf [name=${n}]`).select(); document.execCommand("copy"); toast("Copied ✓") }) };
  const drawColors = () => { $("#clist").innerHTML = colors.length ? colors.map((c, i) => `<span class="chip on"><i style="background:${SHOE_COLORS[c] || "#ccc"}"></i>${esc(c)} <span class="x" onclick="rmColor(${i})">✕</span></span>`).join("") : '<span class="hint">No colours added</span>'; drawGal() };
  const drawGal = () => {
    $("#glist").innerHTML = gal.map((g, i) => `<div class="gal"><div class="pv">${g.s ? `<img src="${esc(imgSrc(g.s))}" alt="">` : "No image"}</div><div class="form" style="gap:8px">
      <div class="filters" style="justify-content:space-between"><b>${i ? "Gallery image #" + (i + 1) : "Main image"}</b><span>${i > 1 ? `<button type="button" class="btn w s" onclick="mvGal(${i},-1)" title="Earlier">↑</button> ` : ""}${i ? `<button type="button" class="btn w s" onclick="mkMain(${i})">Make main</button> <button type="button" class="btn rd s" onclick="rmGal(${i})">${ic("trash")} Remove</button>` : ""}</span></div>
      <div class="row r2"><input type="file" accept="image/*" onchange="galFile(${i},this)"><input placeholder="…or paste an image link (https://)" value="${esc(/^https?:/.test(g.s) ? g.s : "")}" onchange="galUrl(${i},this.value)"></div>
      ${colors.length ? `<div class="chips"><span class="hint">Colour:</span><button type="button" class="chip ${!g.c ? "on" : ""}" onclick="galColor(${i},'')">All colours</button>${colors.map(c => `<button type="button" class="chip ${g.c === c ? "on" : ""}" onclick="galColor(${i},'${esc(c)}')"><i style="background:${SHOE_COLORS[c] || "#ccc"}"></i>${esc(c)}</button>`).join("")}</div>` : ""}</div></div>`).join("");
  };
  window.addColor = c => { c = (c || "").trim(); if (c && !colors.includes(c)) colors.push(c); $("#cnew").value = ""; drawColors() };
  window.rmColor = i => { const c = colors[i]; colors.splice(i, 1); gal.forEach(g => { if (g.c === c) g.c = "" }); drawColors() };
  window.addGal = () => { gal.push({ s: "", c: "" }); drawGal() };
  window.rmGal = i => { gal.splice(i, 1); drawGal() };
  window.mvGal = (i, d) => { [gal[i], gal[i + d]] = [gal[i + d], gal[i]]; drawGal() };
  window.mkMain = i => { const g = gal.splice(i, 1)[0]; gal.unshift(g); drawGal() };
  window.galFile = async (i, inp) => { const f0 = inp.files[0]; if (!f0) return; const p = await upFile(f0); if (p) { gal[i].s = p; drawGal() } };
  window.galUrl = (i, v) => { v = v.trim(); if (v && !/^https:\/\//.test(v)) return toast("Use an https:// link"); gal[i].s = v; drawGal() };
  window.galColor = (i, c) => { gal[i].c = c; drawGal() };
  window._jbColors = () => colors; window._jbMainImg = () => (gal[0] || {}).s || "";
  drawColors();
  $("#pf").onsubmit = async e => {
    e.preventDefault(); const F = new FormData(e.target), g = k => (F.get(k) || "").toString().trim();
    const reg = +g("reg") || 0, sale = +g("sale") || 0;
    if (!gal[0] || !gal[0].s) return toast("Add the main image first");
    if (!reg && !sale) return toast("Add the price");
    const y = { id: x.id, n: g("n"), b: g("b"), gx: g("gx") }; if (g("t")) y.t = g("t");
    if (sale && reg && sale < reg) { y.p = sale; y.o = reg } else y.p = sale || reg;
    y.st = Math.max(0, +g("st") || 0); if (g("sz")) y.sz = g("sz"); if (colors.length) y.cl = colors.join(", ");
    for (const k of ["sd", "d", "sdb", "db", "fb", "fbe", "tg"]) if (g(k)) y[k] = g(k);
    for (const b of ["ft", "fs", "nw", "bs", "off"]) if (F.get(b)) y[b] = 1;
    y.i = gal[0].s; if (gal[0].c) y.ic = gal[0].c;
    const more = gal.slice(1).filter(q => q.s).map(q => ({ s: q.s, c: q.c || "" })); if (more.length) y.g = more;
    const k = g("k"); DB.products[k] = DB.products[k] || [];
    if (f && f.k === k) DB.products[k][f.j] = y; else { if (f) DB.products[f.k].splice(f.j, 1); DB.products[k].unshift(y) }
    const btn = e.target.querySelector("button.btn:not([type=button])"); btn.disabled = true;
    const ok = await save("products"); btn.disabled = false; if (!ok) return;
    let realId = y.id;
    if (isNew && !DEMO) { const L = (DB.products[k] || []).filter(p => p.n === y.n).sort((a, b) => (+b.id.slice(1)) - (+a.id.slice(1))); if (L[0]) realId = L[0].id }
    const cols = F.getAll("col"); let colChanged = false;
    for (const c of DB.collections || []) { const has = (c.ids || []).includes(realId), want = cols.includes(c.slug); if (has !== want) { colChanged = true; c.ids = want ? [...(c.ids || []), realId] : c.ids.filter(i => i !== realId) } }
    if (colChanged) await save("collections");
    toast(isNew ? "Product published ✓" : "Product saved ✓"); location.hash = "products";
  };
}
async function aiWrite(b) {
  const F = new FormData($("#pf")); if (!F.get("n")) return toast("Write the product title first");
  b.disabled = true; const old = b.textContent; b.textContent = "Writing…";
  const reg = +F.get("reg") || 0, sale = +F.get("sale") || 0;
  const j = await api("caption", { n: F.get("n"), b: F.get("b"), k: F.get("k"), gx: F.get("gx"), sz: F.get("sz"), cl: window._jbColors().join(", "), p: sale || reg, o: sale && reg > sale ? reg : 0, img: window._jbMainImg(), tone: $("#aiTone").value });
  b.disabled = false; b.textContent = old;
  if (j.error) return toast(j.error);
  const set = (n, v) => { if (v) $(`#pf [name=${n}]`).value = String(v).trim() };
  set("sd", j.short); set("d", j.description); set("sdb", j.short_bn); set("db", j.description_bn); set("fb", j.fb_post); set("fbe", j.fb_post_en); set("tg", j.tags);
  toast(j.source === "ai" ? "Written by Gemini AI ✓" : "Description written ✓");
}

/* ---------- categories (the shop's main sections: Running, Sneakers, Fashion…) ---------- */
async function Categories(V) {
  title("Categories"); await load("categories", true); await load("products", true);
  const C = cats();
  V.innerHTML = head("Category list", `${C.length} categories. Each one has its own page and menu link in the shop.`, `<button class="btn" onclick="editCat()">${ic("plus")} Add new category</button>`) +
    `<div class="card"><div class="tw"><table class="tbl"><thead><tr><th>Image</th><th>Category name</th><th>Link</th><th>Products</th><th>Order</th><th>Visible</th><th>Action</th></tr></thead><tbody>${C.map(c => `<tr><td>${c.img ? `<img class="th" src="${esc(imgSrc(c.img))}" onerror="this.style.visibility='hidden'">` : '<div class="ph0">—</div>'}</td><td><b>${esc(c.name)}</b><br><span class="mu sm">${esc(c.en || "")}</span></td><td><a class="tag" href="../c/${esc(c.k)}" target="_blank">/c/${esc(c.k)}</a></td><td><span class="tag o">${(DB.products[c.k] || []).length}</span></td><td>${c.order || 0}</td><td>${c.on ? '<span class="tag g">Yes</span>' : '<span class="tag">Hidden</span>'}${c.home ? ' <span class="tag v">Home</span>' : ""}</td><td><div class="acts"><button onclick="editCat('${esc(c.k)}')" title="Edit">${ic("edit")}</button><button class="dl" onclick="delCat('${esc(c.k)}')" title="Delete">${ic("trash")}</button></div></td></tr>`).join("")}</tbody></table></div></div>`;
}
function editCat(k) {
  const c = (DB.categories || []).find(c => c.k === k) || { k: "", name: "", en: "", tag: "", tagb: "", img: "", order: (DB.categories || []).length + 1, on: 1, home: 1 };
  modal(`<div class="mh"><h3>${k ? "Edit category" : "Add new category"}</h3><button class="ic" onclick="closeModal()">✕</button></div><form class="mb form" id="cf">
   <div class="row r3"><div class="fld"><label>Name (Bangla) *</label><input name="name" required value="${esc(c.name)}"></div><div class="fld"><label>Name (English)</label><input name="en" value="${esc(c.en)}" oninput="if(!${!!k})this.form.k.value=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,30)"></div><div class="fld"><label>Link name (a-z)</label><input name="k" required pattern="[a-z0-9\\-]{2,30}" value="${esc(c.k)}" ${k ? "readonly" : ""}></div></div>
   <div class="row r2"><div class="fld"><label>Short line (English)</label><input name="tag" value="${esc(c.tag)}"></div><div class="fld"><label>Short line (Bangla)</label><input name="tagb" value="${esc(c.tagb)}"></div></div>
   <div class="row r3"><div class="fld"><label>Order</label><input name="order" type="number" value="${c.order || 0}"></div><div class="fld"><label>Visible</label><div style="padding-top:6px">${sw("on", c.on)}</div></div><div class="fld"><label>Show on homepage</label><div style="padding-top:6px">${sw("home", c.home)}</div></div></div>
   <div class="fld"><label>Image</label><div class="row r2"><input type="file" accept="image/*" onchange="upFile(this.files[0]).then(p=>{if(p)this.form.img.value=p})"><input name="img" value="${esc(c.img || "")}" placeholder="or image link https://"></div></div>
  </form><div class="mf"><button class="btn w" onclick="closeModal()">Cancel</button><button class="btn" onclick="saveCat('${esc(k || "")}')">Save</button></div>`);
}
async function saveCat(k) {
  const F = new FormData($("#cf")), g = n => (F.get(n) || "").toString().trim();
  const c = { k: g("k"), name: g("name"), en: g("en"), tag: g("tag"), tagb: g("tagb"), order: +g("order") || 0, on: F.get("on") ? 1 : 0, home: F.get("home") ? 1 : 0, img: g("img") };
  if (!/^[a-z0-9-]{2,30}$/.test(c.k) || !c.name) return toast("Name and a link name (2-30 letters) are needed");
  if (!k && (DB.categories || []).some(x => x.k === c.k)) return toast("That link name already exists");
  if (k) Object.assign(DB.categories.find(x => x.k === k), c); else DB.categories.push(c);
  if (await save("categories")) { toast("Category saved ✓"); closeModal(); await load("products", true); route() }
}
async function delCat(k) { if ((DB.products[k] || []).length) return toast("Move or delete its products first"); if (!confirm("Delete this category?")) return; DB.categories = DB.categories.filter(c => c.k !== k); if (await save("categories")) { await load("products", true); route() } }

/* ---------- catalog collections: English name too ---------- */
function editCol(i) {
  const c = i > -1 ? DB.collections[i] : { name: "", en: "", slug: "", ids: [], on: 1 }; const P = allProducts();
  modal(`<div class="mh"><h3>${i > -1 ? "Edit" : "New"} collection</h3><button class="ic" onclick="closeModal()">✕</button></div><form class="mb form" id="colf"><div class="row r3"><div class="fld"><label>Name (Bangla) *</label><input name="name" required value="${esc(c.name)}" placeholder="যেমন ঈদ অফার"></div><div class="fld"><label>Name (English)</label><input name="en" value="${esc(c.en || "")}" placeholder="e.g. Eid Offer"></div><div class="fld"><label>Visible</label><div style="padding-top:6px">${sw("on", c.on)}</div></div></div>
   <div class="fld"><label>Products (${(c.ids || []).length} chosen)</label><input placeholder="Search…" oninput="$$('#colp label').forEach(l=>l.hidden=!l.textContent.toLowerCase().includes(this.value.toLowerCase()))"><div class="pick" id="colp">${P.map(x => `<label><input type="checkbox" name="ids" value="${esc(x.id)}" ${(c.ids || []).includes(x.id) ? "checked" : ""}>${esc(x.n)} <span class="mu sm">· ${esc(catName(x._k))}</span></label>`).join("")}</div></div><span class="hint">Visible collections show as their own row on the homepage.</span></form>
   <div class="mf"><button class="btn w" onclick="closeModal()">Cancel</button><button class="btn" onclick="saveCol(${i})">Save</button></div>`, true);
}
async function saveCol(i) { const F = new FormData($("#colf")); const name = F.get("name").toString().trim(), en = F.get("en").toString().trim(); if (!name) return toast("Give it a name"); const c = { name, en, slug: ((en || name).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "")) || "col" + Date.now().toString(36), ids: F.getAll("ids"), on: F.get("on") ? 1 : 0 }; if (i > -1) DB.collections[i] = c; else DB.collections.push(c); if (await save("collections")) { toast("Collection saved ✓"); closeModal(); route() } }

/* ---------- reviews: shoe wording ---------- */
function editRev(i) {
  const r = i > -1 ? DB.reviews[i] : { name: "", pet: "", text: "", stars: 5, on: 1 };
  modal(`<div class="mh"><h3>${i > -1 ? "Edit" : "Add"} review</h3><button class="ic" onclick="closeModal()">✕</button></div><form class="mb form" id="rf"><div class="row r2"><div class="fld"><label>Customer name & area</label><input name="name" required value="${esc(r.name)}" placeholder="e.g. Rafi, Mirpur"></div><div class="fld"><label>Shoe bought</label><input name="pet" value="${esc(r.pet || "")}" placeholder="e.g. Running shoe, size 42"></div></div><div class="fld"><label>Review</label><textarea name="text" required>${esc(r.text)}</textarea></div><div class="row r2"><div class="fld"><label>Rating</label><select name="stars">${[5, 4, 3, 2, 1].map(n => `<option ${n === r.stars ? "selected" : ""}>${n}</option>`).join("")}</select></div><div class="fld"><label>Visible</label><div style="padding-top:6px">${sw("on", r.on)}</div></div></div><span class="hint">Only add real reviews from your customers. Visible reviews show on the homepage.</span></form><div class="mf"><button class="btn w" onclick="closeModal()">Cancel</button><button class="btn" onclick="saveRev(${i})">Save</button></div>`);
}

/* ---------- hero banners = the colour slides at the top of the homepage ---------- */
function banCard(b) {
  return `<div class="jb-slide" style="background:${grad(b.theme)}"><div class="jb-st"><small>${esc(SHOP)}</small><b>${esc(b.l1)}</b><b>${esc(b.l2)}</b>${b.top || b.big || b.end ? `<span class="jb-pill">${esc(b.top)} <em>${esc(b.big === "auto" ? "XX%" : b.big)}</em> ${esc(b.end)}</span>` : ""}</div>${b.img ? `<img src="${esc(imgSrc(b.img))}" alt="">` : ""}</div>`;
}
async function Banners(V) {
  title("Hero Banners"); const B = await load("banners", true);
  V.innerHTML = head("Hero banners", "The colour slides at the top of the homepage. Turn a slide on or off, change its words, colour, shoe photo and link.", `<a class="btn w" href="../" target="_blank">${ic("eye")} See on the shop</a>`) +
    `<div class="note" style="margin-bottom:14px">Tip: a shoe photo with a clear or white background looks best. "auto" in the big text shows your biggest discount in that category.</div>
    <div class="ban">${B.map((b, i) => `<div class="card${+b.on ? "" : " off"}">${banCard(b)}<div class="card-b"><div class="filters" style="justify-content:space-between"><b>Slide ${b.n}</b>${+b.on ? '<span class="tag g">Showing</span>' : '<span class="tag">Off</span>'}</div><p class="mu sm" style="margin:2px 0 10px">${esc(b.link || "No link")}</p><div class="acts"><button onclick="moveBan(${i},-1)" title="Earlier">↑</button><button onclick="moveBan(${i},1)" title="Later">↓</button><button onclick="togBan(${i})" title="On / off">${ic("eye")}</button><button onclick="editBan(${i})" title="Edit">${ic("edit")}</button></div></div></div>`).join("")}</div>`;
}
function editBan(i) {
  const b = DB.banners[i]; const C = cats();
  modal(`<div class="mh"><h3>Edit slide ${b.n}</h3><button class="ic" onclick="closeModal()">✕</button></div><form class="mb form" id="bf">
   <div id="bpv">${banCard(b)}</div>
   <div class="row r2"><div class="fld"><label>Big line 1</label><input name="l1" maxlength="30" value="${esc(b.l1)}"></div><div class="fld"><label>Big line 2</label><input name="l2" maxlength="30" value="${esc(b.l2)}"></div></div>
   <div class="row r3"><div class="fld"><label>Small text before</label><input name="top" maxlength="30" value="${esc(b.top)}" placeholder="UP TO"></div><div class="fld"><label>Highlight</label><input name="big" maxlength="30" value="${esc(b.big)}" placeholder="auto, ৳75, 30%"></div><div class="fld"><label>Small text after</label><input name="end" maxlength="30" value="${esc(b.end)}" placeholder="OFF"></div></div>
   <div class="fld"><label>Colour</label><div class="chips">${Object.keys(THEMES).map(t => `<label class="chip" style="gap:6px"><input type="radio" name="theme" value="${t}" ${t === b.theme ? "checked" : ""} style="width:14px;height:14px"><i style="background:${grad(t)};width:22px;height:14px;border-radius:5px"></i>${t}</label>`).join("")}</div></div>
   <div class="fld"><label>Shoe photo</label><div class="row r2"><input type="file" accept="image/*" onchange="upFile(this.files[0]).then(p=>{if(p){this.form.img.value=p;banPrev()}})"><input name="img" value="${esc(b.img)}" oninput="banPrev()"></div></div>
   <div class="row r2"><div class="fld"><label>Link when clicked</label><select name="link">${[["shop", "All shoes"], ...C.map(c => ["c/" + c.k, c.name + (c.en ? " · " + c.en : "")]), ["men", "Men"], ["women", "Women"], ["kids", "Kids"]].map(o => `<option value="${o[0]}" ${o[0] === b.link ? "selected" : ""}>${esc(o[1])}</option>`).join("")}${b.link && !["shop", "men", "women", "kids"].includes(b.link) && !C.some(c => "c/" + c.k === b.link) ? `<option selected value="${esc(b.link)}">${esc(b.link)}</option>` : ""}</select></div><div class="fld"><label>Showing</label><div style="padding-top:6px">${sw("on", +b.on)}</div></div></div>
  </form><div class="mf"><button class="btn w" onclick="closeModal()">Cancel</button><button class="btn" onclick="saveBan(${i})">Save slide</button></div>`, true);
  $("#bf").oninput = banPrev; $("#bf").onchange = banPrev;
}
function banForm() { const F = new FormData($("#bf")); const o = {}; for (const k of ["l1", "l2", "top", "big", "end", "theme", "img", "link"]) o[k] = (F.get(k) || "").toString().trim(); o.on = F.get("on") ? "1" : "0"; return o }
function banPrev() { $("#bpv").innerHTML = banCard(banForm()) }
async function saveBan(i) { Object.assign(DB.banners[i], banForm()); if (await save("banners")) { toast("Slide saved ✓"); closeModal(); route() } }
async function togBan(i) { const b = DB.banners[i]; b.on = +b.on ? "0" : "1"; if (await save("banners")) route() }
async function moveBan(i, d) {
  const B = DB.banners, k = i + d; if (k < 0 || k >= B.length) return;
  const a = { ...B[i] }, c = { ...B[k] }; a.n = B[k].n; c.n = B[i].n; B[i] = c; B[k] = a;
  if (await save("banners")) route();
}

/* ---------- homepage CMS ---------- */
async function CMS(V) {
  title("Homepage CMS"); const S = await load("site", true); const c = S.cms = S.cms || {}; const C = cats();
  const links = [["shop", "All shoes"], ...C.map(x => ["c/" + x.k, x.name + (x.en ? " · " + x.en : "")]), ["men", "Men"], ["women", "Women"], ["kids", "Kids"]];
  const tile = n => `<div class="box"><b>Tile ${n}</b><div class="row r2" style="margin-top:8px"><div class="fld"><label>Text (Bangla)</label><input name="cms.ht${n}_bn" value="${esc(c["ht" + n + "_bn"] || "")}"></div><div class="fld"><label>Text (English)</label><input name="cms.ht${n}_en" value="${esc(c["ht" + n + "_en"] || "")}"></div></div>
    <div class="row r2"><div class="fld"><label>Link</label><select name="cms.ht${n}_link">${links.map(o => `<option value="${o[0]}" ${o[0] === c["ht" + n + "_link"] ? "selected" : ""}>${esc(o[1])}</option>`).join("")}</select></div><div class="fld"><label>Colour</label><select name="cms.ht${n}_color">${Object.keys(THEMES).filter(t => t !== "maroon" && t !== "green").map(t => `<option ${t === c["ht" + n + "_color"] ? "selected" : ""}>${t}</option>`).join("")}</select></div></div>
    <div class="fld"><label>Shoe photo</label><div class="row r2"><input type="file" accept="image/*" onchange="upFile(this.files[0]).then(p=>{if(p){this.form['cms.ht${n}_img'].value=p;this.closest('.box').querySelector('img').src=imgSrc(p)}})"><input name="cms.ht${n}_img" value="${esc(c["ht" + n + "_img"] || "")}"></div><img src="${esc(imgSrc(c["ht" + n + "_img"] || ""))}" alt="" style="height:80px;margin-top:6px;object-fit:contain;background:${grad(c["ht" + n + "_color"])};border-radius:12px;padding:6px"></div></div>`;
  const two = (k, l, area) => `<div class="row r2"><div class="fld"><label>${l} (English)</label>${area ? `<textarea name="cms.${k}">${esc(c[k] || "")}</textarea>` : `<input name="cms.${k}" value="${esc(c[k] || "")}">`}</div><div class="fld"><label>${l} (Bangla)</label>${area ? `<textarea name="cms.${k}_bn">${esc(c[k + "_bn"] || "")}</textarea>` : `<input name="cms.${k}_bn" value="${esc(c[k + "_bn"] || "")}">`}</div></div>`;
  V.innerHTML = head("Homepage content", "Edit the words, tiles and messages on your homepage", `<a class="btn w" href="../" target="_blank">${ic("eye")} See on the shop</a>`) + `<form class="form" id="cmsf">
   <div class="card"><div class="card-h"><div><h3>Top bar message</h3><p>A thin moving line above the header. Off by default.</p></div>${sw("cms.announce_on", c.announce_on === "1")}</div><div class="card-b form">${two("announce", "Announcement")}<span class="hint">Separate messages with •</span></div></div>
   <div class="card"><div class="card-h"><div><h3>3 big tiles under the slides</h3><p>"Shop by style" tiles</p></div>${sw("cms.ht_on", c.ht_on === "1")}</div><div class="card-b form"><div class="row r2"><div class="fld"><label>Title (Bangla)</label><input name="cms.ht_title" value="${esc(c.ht_title || "")}"></div><div class="fld"><label>Title (English)</label><input name="cms.ht_title_en" value="${esc(c.ht_title_en || "")}"></div></div><div class="grid g3" style="gap:12px">${tile(1)}${tile(2)}${tile(3)}</div></div></div>
   <div class="card"><div class="card-h"><h3>New collection banner</h3></div><div class="card-b form">${two("promo_title", "Title")}${two("promo_sub", "Text")}${two("promo_btn", "Button")}</div></div>
   <div class="card"><div class="card-h"><h3>About us</h3></div><div class="card-b form">${two("about", "About text", 1)}</div></div>
   <div class="card"><div class="card-h"><div><h3>WhatsApp pop-up</h3><p>Small message that opens after a few seconds</p></div></div><div class="card-b form">${two("wa_popup", "Message")}<div class="row r2"><div class="fld"><label>Show after (seconds, 0 = never)</label><input type="number" name="cms.wa_popup_sec" value="${esc(c.wa_popup_sec || "6")}"></div><div class="fld"><label>Size guide on product pages</label><div style="padding-top:6px">${sw("cms.size_guide", c.size_guide === "1")}</div></div></div></div></div>
   <div class="card"><div class="card-h"><h3>Footer</h3></div><div class="card-b form">${two("footer_note", "Footer line")}</div></div>
   <div><button class="btn">${ic("check")} Save homepage</button></div></form>`;
  $("#cmsf").onsubmit = async e => {
    e.preventDefault();
    for (const el of e.target.elements) { if (!el.name || !el.name.startsWith("cms.")) continue; c[el.name.slice(4)] = el.type === "checkbox" ? (el.checked ? "1" : "0") : el.value.trim() }
    if (await save("site")) toast("Homepage saved ✓");
  };
}

/* ---------- customers: also the shop's login accounts ---------- */
const _Customers = window.Customers;
window.Customers = async function (V) {
  await _Customers(V);
  const j = await api("accounts"); const A = j.data || []; if (!A.length) return;
  const box = document.createElement("div"); box.className = "card"; box.style.marginTop = "16px";
  box.innerHTML = `<div class="card-h"><div><h3>Login accounts (${A.length})</h3><p>Customers who made an account on the website</p></div></div><div class="tw"><table class="tbl"><thead><tr><th>Name</th><th>Phone</th><th>Joined</th><th>Last login</th><th>Status</th><th>Action</th></tr></thead><tbody>${A.map(a => `<tr><td><b>${esc(a.name)}</b></td><td>${esc(a.phone)}</td><td class="mu sm">${esc((a.created || "").slice(0, 16))}</td><td class="mu sm">${esc((a.last_login || "—").slice(0, 16))}</td><td>${+a.blocked ? '<span class="tag r">Blocked</span>' : '<span class="tag g">Active</span>'}</td><td><div class="filters"><button class="btn w s" onclick="accOp(${a.id},'block')">${+a.blocked ? "Unblock" : "Block"}</button><button class="btn w s" onclick="accPw(${a.id})">New password</button></div></td></tr>`).join("")}</tbody></table></div>`;
  V.append(box);
};
async function accOp(id, op, extra = {}) { const j = await api("account_op", { id, op, ...extra }); if (j.error) return toast(j.error); toast("Updated ✓"); route() }
function accPw(id) { const p = prompt("New password for this customer (6+ characters):"); if (p) accOp(id, "pw", { pass: p }) }

/* ---------- Ask AI ---------- */
function askAI() {
  modal(`<div class="mh"><h3>✦ Ask AI</h3><button class="ic" onclick="closeModal()">✕</button></div><div class="mb form"><p class="mu" style="margin:0">Quick help for your shoe shop. With a Gemini key (Site Settings › Google SEO) it uses Google AI; otherwise it gives built-in tips.</p>
   <div class="chips">${["Write a Facebook post for a sneaker offer", "Ideas to sell more running shoes this month", "Write an SEO description for my shoe shop", "Reply to a customer asking about delivery time", "Reply to a customer asking which size to buy"].map(s => `<button type="button" class="chip" onclick="$('#aiq').value=this.textContent">${s}</button>`).join("")}</div>
   <textarea id="aiq" placeholder="Ask anything about your shop…"></textarea><div><button class="btn vi" onclick="runAI(this)">✦ Ask</button></div><div id="aia" class="box" hidden style="white-space:pre-wrap"></div></div>`);
}
async function runAI(b) {
  const q = $("#aiq").value.trim(); if (!q) return; const S = (DB.site || {}).store || {}, D = (DB.site || {}).delivery || {}, name = S.name_en || SHOP;
  b.disabled = true; const j = await api("ai", { prompt: `You help an online shoe shop in Bangladesh called ${name} (running shoes, sneakers, fashion shoes; cash on delivery; delivery ৳${D.inside || 75} inside Dhaka, ৳${D.outside || 135} outside; phone ${S.phone || ""}). Answer briefly in simple language (Bangla if the question is in Bangla).\n\n` + q }); b.disabled = false;
  const fallback = /facebook|post/i.test(q) ? `👟 স্নিকার অফার চলছে ${name}-এ!\n\n✨ আরামদায়ক আর হালকা\n✨ নতুন সব ডিজাইন\n🚚 ঢাকায় ৳${D.inside || 75}, ঢাকার বাইরে ৳${D.outside || 135}\n💵 ক্যাশ অন ডেলিভারি\n📞 অর্ডার: ${S.phone || ""}\n\n#JutarBazarBD #Sneakers #Shoes #Dhaka`
    : /seo/i.test(q) ? `${name} is an online shoe shop in Bangladesh. Buy running shoes, sneakers and fashion shoes for men, women and kids with cash on delivery all over Bangladesh and easy size exchange.`
    : /size/i.test(q) ? `ধন্যবাদ! আপনার পায়ের দৈর্ঘ্য (গোড়ালি থেকে বুড়ো আঙুল, সেন্টিমিটারে) জানালে আমরা সঠিক সাইজ মিলিয়ে দেব। সাইজ না মিললে সহজে বদলে নিতে পারবেন। – ${name}`
    : /delivery/i.test(q) ? `ধন্যবাদ! ঢাকার ভেতরে ১-২ দিনে আর ঢাকার বাইরে ২-৪ দিনে ডেলিভারি হয়। ক্যাশ অন ডেলিভারি, হাতে পেয়ে টাকা দিন। – ${name}`
    : `Ideas:\n• Pin 3-4 shoes as "Flash sale" and add them to a collection\n• Post a short video of each new pair on Facebook and TikTok\n• Offer free delivery over a set amount for a weekend\n• Message repeat buyers when their size is back in stock`;
  $("#aia").hidden = false; $("#aia").textContent = j.text || fallback;
}

/* ---------- Bangla for the shoe-shop words ---------- */
Object.assign(BN, {
  "Shoe Shop Admin Panel": "জুতার দোকানের অ্যাডমিন প্যানেল", "Details, photos, sizes, colours and price": "বিস্তারিত, ছবি, সাইজ, রঙ আর দাম", "View in shop": "দোকানে দেখুন",
  "For whom": "কাদের জন্য", "Men": "ছেলেদের", "Women": "মেয়েদের", "Kids": "বাচ্চাদের", "Men & Women": "ছেলে ও মেয়ে দুজনেরই", "Shows on the Men / Women / Kids pages.": "ছেলেদের / মেয়েদের / বাচ্চাদের পেজে দেখাবে।",
  "Regular price (৳) *": "রেগুলার দাম (৳) *", "Stock (pairs)": "স্টক (জোড়া)", "Available sizes": "যে সাইজগুলো আছে", "Tap to add or remove:": "চাপ দিয়ে যোগ বা বাদ দিন:", "Kids:": "বাচ্চাদের:",
  "Customers must pick one of these sizes when ordering.": "অর্ডারের সময় কাস্টমারকে এর একটা সাইজ বাছতে হবে।", "Colours": "রঙ", "No colours added": "কোনো রঙ যোগ করা হয়নি",
  "First image is the main photo. Phone camera photos are fine: the site makes them small and turns them the right way up. Tag a colour so the right photo shows when a customer picks that colour.": "ফোনের ক্যামেরার ছবিই দিন: সাইট নিজেই ছোট করে আর সোজা করে নেয়। রঙ ট্যাগ দিলে কাস্টমার যে রঙ বাছবে সেই ছবি দেখাবে।",
  "Image 1 required": "১ নম্বর ছবি লাগবেই", "Make main": "মেইন ছবি করুন", "Earlier": "আগে",
  "Writes English + Bangla description, Facebook post and tags from the title, photo, sizes and price. Works without a key; with a free Gemini key it also looks at the photo.": "নাম, ছবি, সাইজ আর দাম দেখে ইংরেজি + বাংলা বিবরণ, ফেসবুক পোস্ট আর ট্যাগ লিখে দেয়। কী ছাড়াই কাজ করে; ফ্রি Gemini কী দিলে ছবিও দেখে লেখে।",
  "Premium": "প্রিমিয়াম", "Offer / sale": "অফার / সেল", "Eid / festive": "ঈদ / উৎসব", "Friendly": "বন্ধুসুলভ", "Simple": "সহজ", "Writing…": "লিখছে…",
  "Short description (English)": "ছোট বিবরণ (ইংরেজি)", "Short description (Bangla)": "ছোট বিবরণ (বাংলা)", "Full description (English)": "পূর্ণ বিবরণ (ইংরেজি)", "Full description (Bangla)": "পূর্ণ বিবরণ (বাংলা)",
  "Leave the descriptions empty and they are written automatically when you save.": "বিবরণ খালি রাখলে সেভ করার সময় নিজে থেকেই লেখা হয়ে যাবে।", "Facebook post (Bangla)": "ফেসবুক পোস্ট (বাংলা)", "Facebook post (English)": "ফেসবুক পোস্ট (ইংরেজি)", "Copy": "কপি", "Copied ✓": "কপি হয়েছে ✓", "Nothing to copy yet": "কপি করার মতো কিছু নেই",
  "Search tags": "সার্চ ট্যাগ", "(comma separated)": "(কমা দিয়ে আলাদা)", "Add the main image first": "আগে মেইন ছবি দিন", "Add the price": "দাম দিন",
  "Category list": "ক্যাটাগরির তালিকা", "Link": "লিংক", "Home": "হোম", "Name (Bangla) *": "নাম (বাংলা) *", "Name (English)": "নাম (ইংরেজি)", "Link name (a-z)": "লিংকের নাম (a-z)",
  "Short line (English)": "ছোট লাইন (ইংরেজি)", "Short line (Bangla)": "ছোট লাইন (বাংলা)", "Show on homepage": "হোমপেজে দেখান", "Name and a link name (2-30 letters) are needed": "নাম আর লিংকের নাম (২-৩০ অক্ষর) লাগবে", "That link name already exists": "এই লিংকের নাম আগে থেকেই আছে",
  "Visible collections show as their own row on the homepage.": "চালু থাকা কালেকশন হোমপেজে আলাদা সারিতে দেখায়।", "Shoe bought": "কোন জুতা কিনেছেন", "Only add real reviews from your customers. Visible reviews show on the homepage.": "শুধু কাস্টমারদের আসল রিভিউ দিন। চালু থাকা রিভিউ হোমপেজে দেখায়।",
  "The colour slides at the top of the homepage. Turn a slide on or off, change its words, colour, shoe photo and link.": "হোমপেজের উপরের রঙিন স্লাইডগুলো। স্লাইড চালু/বন্ধ করুন, লেখা, রঙ, জুতার ছবি আর লিংক বদলান।", "See on the shop": "দোকানে দেখুন",
  "Tip: a shoe photo with a clear or white background looks best. \"auto\" in the big text shows your biggest discount in that category.": "টিপস: সাদা বা স্বচ্ছ ব্যাকগ্রাউন্ডের জুতার ছবি সবচেয়ে সুন্দর লাগে। বড় লেখায় \"auto\" দিলে ওই ক্যাটাগরির সবচেয়ে বড় ছাড় দেখায়।",
  "Showing": "দেখাচ্ছে", "Off": "বন্ধ", "On / off": "চালু / বন্ধ", "Big line 1": "বড় লাইন ১", "Big line 2": "বড় লাইন ২", "Small text before": "আগের ছোট লেখা", "Highlight": "হাইলাইট", "Small text after": "পরের ছোট লেখা", "Colour": "রঙ", "Shoe photo": "জুতার ছবি", "Save slide": "স্লাইড সেভ করুন", "Slide saved ✓": "স্লাইড সেভ হয়েছে ✓", "All shoes": "সব জুতা",
  "Edit the words, tiles and messages on your homepage": "হোমপেজের লেখা, টাইল আর মেসেজ বদলান", "Top bar message": "উপরের বারের মেসেজ", "Announcement": "ঘোষণা", "(separate messages with •)": "(মেসেজগুলো • দিয়ে আলাদা করুন)", "Separate messages with •": "মেসেজগুলো • দিয়ে আলাদা করুন", "A thin moving line above the header. Off by default.": "হেডারের উপরে একটা চলন্ত লাইন। শুরুতে বন্ধ থাকে।", "Announcement (English)": "ঘোষণা (ইংরেজি)", "Announcement (Bangla)": "ঘোষণা (বাংলা)", "3 big tiles under the slides": "স্লাইডের নিচের ৩টা বড় টাইল", "\"Shop by style\" tiles": "\"স্টাইল দেখে কিনুন\" টাইল",
  "Title (Bangla)": "শিরোনাম (বাংলা)", "Title (English)": "শিরোনাম (ইংরেজি)", "Text (Bangla)": "লেখা (বাংলা)", "Text (English)": "লেখা (ইংরেজি)", "New collection banner": "নতুন কালেকশনের ব্যানার", "Title (English) ": "শিরোনাম (ইংরেজি)", "Button (English)": "বাটন (ইংরেজি)", "Button (Bangla)": "বাটন (বাংলা)",
  "About us": "আমাদের সম্পর্কে", "About text (English)": "পরিচিতি (ইংরেজি)", "About text (Bangla)": "পরিচিতি (বাংলা)", "WhatsApp pop-up": "হোয়াটসঅ্যাপ পপ-আপ", "Small message that opens after a few seconds": "কয়েক সেকেন্ড পর যে ছোট মেসেজ খোলে", "Message (English)": "মেসেজ (ইংরেজি)", "Message (Bangla)": "মেসেজ (বাংলা)",
  "Show after (seconds, 0 = never)": "কত সেকেন্ড পর দেখাবে (০ = কখনো না)", "Size guide on product pages": "প্রোডাক্ট পেজে সাইজ গাইড", "Footer": "ফুটার", "Footer line (English)": "ফুটারের লাইন (ইংরেজি)", "Footer line (Bangla)": "ফুটারের লাইন (বাংলা)", "Homepage saved ✓": "হোমপেজ সেভ হয়েছে ✓",
  "Login accounts": "লগইন অ্যাকাউন্ট", "Customers who made an account on the website": "যে কাস্টমাররা ওয়েবসাইটে অ্যাকাউন্ট খুলেছেন", "Joined": "যোগ দিয়েছেন", "Last login": "শেষ লগইন", "Blocked": "ব্লক করা", "Block": "ব্লক", "Unblock": "আনব্লক", "New password": "নতুন পাসওয়ার্ড", "Updated ✓": "আপডেট হয়েছে ✓",
  "Quick help for your shoe shop. With a Gemini key (Site Settings › Google SEO) it uses Google AI; otherwise it gives built-in tips.": "আপনার জুতার দোকানের জন্য দ্রুত সাহায্য। Gemini কী থাকলে (সাইট সেটিংস › গুগল SEO) Google AI ব্যবহার করে; না থাকলে নিজের টিপস দেয়।",
  "Write a Facebook post for a sneaker offer": "স্নিকার অফারের জন্য ফেসবুক পোস্ট লিখুন", "Ideas to sell more running shoes this month": "এই মাসে বেশি রানিং শু বিক্রির আইডিয়া", "Write an SEO description for my shoe shop": "আমার জুতার দোকানের জন্য SEO বিবরণ লিখুন", "Reply to a customer asking which size to buy": "কোন সাইজ নেবে জানতে চাওয়া কাস্টমারকে উত্তর",
  "Store name (Bangla)": "দোকানের নাম (বাংলা)", "Store name (English)": "দোকানের নাম (ইংরেজি)", "Shop address (English)": "দোকানের ঠিকানা (ইংরেজি)", "Shop address (Bangla)": "দোকানের ঠিকানা (বাংলা)", "Instagram link": "ইনস্টাগ্রাম লিংক", "TikTok link": "টিকটক লিংক", "YouTube link": "ইউটিউব লিংক",
  "Email for new-order alerts": "নতুন অর্ডারের খবর যাবে যে ইমেইলে", "Logo": "লোগো", "(empty = shoe icon)": "(খালি = জুতার আইকন)", "Name shown for inside Dhaka": "ঢাকার ভেতরের জন্য যে নাম দেখাবে", "Name shown for outside Dhaka": "ঢাকার বাইরের জন্য যে নাম দেখাবে", "Delivery time note": "ডেলিভারি সময়ের নোট", "(shows at checkout)": "(চেকআউটে দেখায়)",
  "Delivery time note (Bangla)": "ডেলিভারি সময়ের নোট (বাংলা)", "Instructions for mobile payment (Bangla)": "মোবাইল পেমেন্টের নির্দেশনা (বাংলা)", "Send money, then write the TrxID": "সেন্ড মানি করে TrxID লিখুন", "Meta description (Bangla)": "মেটা বিবরণ (বাংলা)", "Full backup": "পূর্ণ ব্যাকআপ", "All products, orders, settings and uploaded photos in one zip. Keep one every month.": "সব প্রোডাক্ট, অর্ডার, সেটিংস আর আপলোড করা ছবি এক zip-এ। প্রতি মাসে একটা রেখে দিন।", "Download backup": "ব্যাকআপ ডাউনলোড",
  "Forgot the password? Another super admin can set a new one in Admin & Roles.": "পাসওয়ার্ড ভুলে গেছেন? আরেকজন সুপার অ্যাডমিন Admin & Roles থেকে নতুন পাসওয়ার্ড দিতে পারবেন।", "Description written ✓": "বিবরণ লেখা হয়েছে ✓", "Written by Gemini AI ✓": "Gemini AI লিখে দিয়েছে ✓",
  "All colours": "সব রঙ", "Colour:": "রঙ:", "Tan": "ট্যান", "Navy": "নেভি", "Maroon": "মেরুন", "Cream": "ক্রিম", "Olive": "অলিভ", "Recent admin activity": "সাম্প্রতিক অ্যাডমিন কাজ", "No reviews yet.": "এখনো কোনো রিভিউ নেই।", "No collections yet. Make them in Catalog Collections.": "এখনো কোনো কালেকশন নেই। Catalog Collections থেকে বানান।",
  "Hand-picked groups (Eid Offer, Best Sellers, Office Wear…) that show as their own row on the homepage": "বাছাই করা জুতার গ্রুপ (ঈদ অফার, বেস্ট সেলার, অফিস ওয়্যার…) যা হোমপেজে আলাদা সারিতে দেখায়", "Courier Hub (Steadfast)": "কুরিয়ার হাব (Steadfast)",
  "Each one has its own page and menu link in the shop.": "প্রতিটার দোকানে নিজের পেজ আর মেনু লিংক আছে।", "Image": "ছবি", "Slide": "স্লাইড", "Tile": "টাইল"
});
BNR.push([/^(\d+) categories\. Each one has its own page and menu link in the shop\.$/, (m, a) => a + "টা ক্যাটাগরি। প্রতিটার দোকানে নিজের পেজ আর মেনু লিংক আছে।"], [/^(\d+) customers from your orders$/, (m, a) => "অর্ডার থেকে " + a + " জন কাস্টমার"], [/^from (.+)$/, (m, a) => "· " + a], [/^Slide (\d+)$/, (m, a) => "স্লাইড " + a], [/^Tile (\d+)$/, (m, a) => "টাইল " + a], [/^Edit slide (\d+)$/, (m, a) => "স্লাইড " + a + " এডিট"], [/^Login accounts \((\d+)\)$/, (m, a) => "লগইন অ্যাকাউন্ট (" + a + ")"]);

boot();
