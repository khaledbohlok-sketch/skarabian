/*
 * SK Arabian Studio — editor and document viewer inside the SK Arabians Management System.
 * The form builders are the original Studio's (same fields and wording); the old "saved horses / employees"
 * lists are replaced by the system's records, which the server fills in (Studio::prefill).
 */
(function(){
'use strict';
const DATA=document.getElementById('studio-data');if(!DATA||!window.SKStudio)return;
const CFG=JSON.parse(DATA.textContent),SK=window.SKStudio,H=SK.helpers,T=CFG.i18n||{};
const {esc,money,n2,fmtDate,today,addDays,addMonths,dayDiff,payNet,vetUpcoming,vetStatus,foalDate,boardEnd,eosCalc,lenText,pedLabel,SEX,VT,METH,PREG,INC,LEAVE,EOSR,LTYPE,HSTAT,PLANST,EMBST,CUR,EST,CHK,low,clone}=H;
const $=s=>document.querySelector(s);
/* Strips thousands separators / stray characters from a money field's typed text and rounds to 2
 * decimals — the only thing ever written into the data model for a money column (display formatting
 * with money() happens separately, only while the field isn't focused). */
function moneyParse(v){const n=parseFloat(String(v).replace(/[^0-9.]/g,''));return isNaN(n)?"":Math.max(0,Math.round(n*100)/100)}
const CUR_DOC=CFG.type,S={},host=$('#pages'),canvas=$('#canvas'),ed=$('#editor');
const csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||CFG.csrf||'';
let LH=+CFG.letterhead||2;

/* ---------- Arabic interface ----------
 * The form builders below write their labels in English (as in the original Studio). In an Arabic session the
 * server sends lang/studio-ui-ar.php as CFG.uiAr, and trUi() swaps each drawn label, heading, hint, button,
 * option and placeholder for its Arabic text (that file explains its key forms). Only interface elements are
 * looked at, never typed data or the names inside the document lines. */
const UI_AR=CFG.lang==='ar',UI_LANG=UI_AR?'ar':'en',DICT=CFG.uiAr||null;
const OL=v=>UI_AR&&v[1]?v[1]:v[0];   // an [English, Arabic] pair from the shared lists, in the interface language
const PATS=DICT?Object.keys(DICT).filter(k=>k.includes('%1')).map(k=>[new RegExp('^'+k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&').replace(/%[12]/g,'(.+?)')+'$'),DICT[k]]):[];
function trS(s,tag){
  if(!DICT||s==null)return s;
  const t=String(s).trim();if(!t||!/[A-Za-z]/.test(t))return s;
  let r=(tag&&DICT[tag+'|'+t])||DICT[t],m;
  if(!r)for(const[re,v]of PATS){if((m=re.exec(t))){r=v.replace('%1',m[1]).replace('%2',m[2]||'');break}}
  if(!r){
    if((m=/^([+⟳]\s*)(.+)$/.exec(t))&&DICT[m[2]])r=m[1]+DICT[m[2]];                               // "+ Add item"
    else if((m=/^(\S+) (\d+) · (.*)$/.exec(t))&&DICT[m[1]])r=`${DICT[m[1]]} ${m[2]} · ${m[3]==='Untitled'?DICT.Untitled:m[3]}`; // "Clause 2 · …"
    else if((m=/^(.*\S) (\d+)$/.exec(t))&&DICT[m[1]])r=`${DICT[m[1]]} ${m[2]}`;                    // "Description 3"
  }
  return r?String(s).replace(t,r):s;
}
const UI_SEL='label,h2,h3,.hint,button,option,th,.pill,.manual-tag,.sum,.calc span,.card-h>span:first-child,.ph-empty,.fin-empty,.st,em';
function trUi(root){
  if(!DICT||!root)return;
  const w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);
  for(let n=w.nextNode();n;n=w.nextNode()){
    const p=n.parentElement;if(!p||!p.matches(UI_SEL))continue;
    const own=p.tagName==='LABEL'&&p.htmlFor&&DICT['#'+p.htmlFor];
    const v=own?n.nodeValue.replace(n.nodeValue.trim(),own):trS(n.nodeValue,p.tagName.toLowerCase());
    if(v!==n.nodeValue)n.nodeValue=v}
  root.querySelectorAll('[placeholder],[title],[aria-label],[data-label]').forEach(el=>{
    for(const a of['placeholder','title','aria-label','data-label']){const v=el.getAttribute(a);if(v){const t=trS(v);if(t!==v)el.setAttribute(a,t)}}});
}

/* ---------- initial data ---------- */
/* Maps one document_bills row (server, snake_case) to the shape ED.fin / R.fin work with. Bill-linked
 * fields (number, date, due, party and category names) are blank for a manual line; manual's own en/ar/desc fill in instead. */
function finLineFromServer(r){
  return {lineId:+r.id,isManual:!!(+r.is_manual),billId:r.bill_id?+r.bill_id:null,
    number:r.number||"",date:r.bill_date||"",due:r.due_date||"",
    partyEn:r.party_name_en||"",partyAr:r.party_name_ar||"",catEn:r.category_name_en||"",catAr:r.category_name_ar||"",
    desc:r.description||"",en:r.manual_label_en||"",ar:r.manual_label_ar||"",
    type:r.bill_type||"expense",status:r.bill_status||"pending",
    currency:r.currency||"QAR",amtOriginal:r.amount_original!=null?+r.amount_original:null,
    amt:+r.amount_qar||0,paid:+r.paid_qar||0};
}
function initial(){
  const D=SK.defaults()[CUR_DOC]||{};
  if(CFG.saved)return Object.assign(clone(D),CFG.saved);
  const tpl=Object.assign(clone(D),CFG.tpl||{});
  if(CFG.mode==='template')return tpl;
  let d=SK.clean(CUR_DOC,tpl);
  if(d===tpl||!Object.keys(d).length)d=tpl;
  ['no','ref','rno'].forEach(k=>{if(k in d)d[k]=''});   // the archive reference is given when the document is issued
  // settings that the "clean document" step empties but the templates need
  ['window','filter','output','showQid','title','titleAr','status','salCur','checkResult'].forEach(k=>{if(k in D&&(d[k]===''||d[k]==null)&&D[k]!=='')d[k]=clone(D[k])});
  Object.assign(d,CFG.prefill||{});
  if('lang' in D&&!(CFG.prefill||{}).lang)d.lang=CFG.lang==='ar'?'ar':d.lang||'en';
  return d;
}
S[CUR_DOC]=initial();
// document_bills (server) is the only source of truth for a fin draft's lines — CFG.saved never carries
// a "rows" key for 'fin', so without this, S.fin.rows would silently fall back to the defaults' sample data.
if(CUR_DOC==="fin"&&CFG.mode==="draft")S.fin.rows=(CFG.finLines||[]).map(finLineFromServer);

/* ---------- form helpers (original Studio) ---------- */
/* Phone fields (by document): typed as a phone (dir ltr, phone keypad), a Qatar number tidied to "+974 5555 5555" when
   the field is left; anything else stays as typed. */
const PHONES={po:["phone"],inv:["cPhone"],cover:["ownerPhone"],board:["ownerPhone"],letters:["contact"]};
function tidyPhone(v){const s=String(v||"").trim(),d=s.replace(/[^\d]/g,"");if(/^(00)?974\d{8}$/.test(d)||/^\+974/.test(s)&&d.length===11)return "+974 "+d.slice(-8,-4)+" "+d.slice(-4);if(d.length===8&&!/[a-z]/i.test(s))return "+974 "+d.slice(0,4)+" "+d.slice(4);return s}
const fld=(k,label,o={})=>{const v=S[CUR_DOC][k]??"";const id=`f-${CUR_DOC}-${k}`;const dir=o.ar?' dir="rtl"':"";const cls=`f${o.wide?" wide":""}`;
  if(!o.type&&!o.opts&&!o.area&&(PHONES[CUR_DOC]||[]).includes(k))return `<div class="${cls}"><label for="${id}">${label}</label><input id="${id}" class="in" data-k="${k}" data-phone="1" type="tel" inputmode="tel" autocomplete="off" dir="ltr" value="${esc(v)}" placeholder="+974 5555 5555"></div>`;
  if(o.area)return `<div class="${cls}"><label for="${id}">${label}</label><textarea id="${id}" class="in" data-k="${k}" rows="${o.area}"${dir}>${esc(v)}</textarea></div>`;
  if(o.opts)return `<div class="${cls}"><label for="${id}">${label}</label><select id="${id}" data-k="${k}">${o.opts.map(([a,b])=>`<option value="${a}"${String(v)===String(a)?" selected":""}>${b}</option>`).join("")}</select></div>`;
  return `<div class="${cls}"><label for="${id}">${label}</label><input id="${id}" class="in" data-k="${k}" type="${o.type||"text"}" value="${esc(v)}"${o.type==="number"?' step="any"':""}${dir}${o.ph?` placeholder="${esc(o.ph)}"`:""}></div>`};
const sec=(t,inner,one)=>`<div class="sec"><h3>${t}</h3><div class="grid${one?" one":""}">${inner}</div></div>`;
const langSeg=()=>{const v=S[CUR_DOC].lang;return `<div class="seg" role="group" aria-label="Document language"><button type="button" data-lang="en" aria-pressed="${v==="en"}">English</button><button type="button" data-lang="ar" aria-pressed="${v==="ar"}">العربية</button></div>`};
/* opts.total adds a read-only per-line Total column + a grand-total footer row:
 *   {qty:'colKey', price:'colKey'}  — line total = qty × price (PO/invoice items)
 *   {calc: row => number}           — line total = a custom formula (e.g. payslip net pay)
 * plus optional label, currency ('colKey on the document'), hideGrand (when a summary already exists
 * elsewhere on the page, e.g. PO/invoice's own Subtotal/Total line). */
// an amount's currency on screen: "QR" in English (as the documents), "ر.ق" in Arabic
const curUi=c=>UI_AR?(CUR[c]||CUR.QAR).arCode:(!c||c==="QAR"?"QR":c);
function listEd(key,cols,addLabel,minw,opts){
  opts=opts||{};
  const rows=S[CUR_DOC][key]||(S[CUR_DOC][key]=[]);
  const tot=opts.total,ar=UI_AR;
  const lineCalc=tot&&(tot.calc||(r=>n2(r[tot.qty])*n2(r[tot.price])));
  const cur=tot&&tot.currency?(S[CUR_DOC][tot.currency]||""):"";
  const totLabel=tot&&tot.label||(ar?"الإجمالي":"Total");
  const head=cols.map(c=>`<th style="${c.w?`width:${c.w}`:""}">${c.label}</th>`).join("")+(tot?`<th style="width:120px">${esc(totLabel)}</th>`:"")+"<th></th>";
  const body=rows.map((r,i)=>{
    const cells=cols.map(c=>{
      if(c.opts)return `<td data-label="${esc(c.label)}"><select data-l="${key}" data-i="${i}" data-c="${c.c}" aria-label="${c.label}">${c.opts.map(([a,b])=>`<option value="${a}"${r[c.c]===a?" selected":""}>${b}</option>`).join("")}</select></td>`;
      if(c.money){const v=r[c.c];return `<td data-label="${esc(c.label)}" class="lt-money"><input data-l="${key}" data-i="${i}" data-c="${c.c}" data-money="1" aria-label="${c.label} ${i+1}" inputmode="decimal" dir="ltr" value="${esc(v===""||v==null?"":money(v))}"></td>`}
      return `<td data-label="${esc(c.label)}"><input data-l="${key}" data-i="${i}" data-c="${c.c}" aria-label="${c.label} ${i+1}" value="${esc(r[c.c])}"${c.num?' type="number" step="any" min="0"':""}${c.date?' type="date"':""}${c.ar?' dir="rtl"':""}></td>`;
    }).join("");
    const totalCell=tot?`<td class="lt-total" data-label="${esc(totLabel)}">${money(lineCalc(r))}</td>`:"";
    const hasData=cols.some(c=>String(r[c.c]==null?"":r[c.c]).trim()!=="");
    const ctl=`<td class="ctl">`
      +(i>0?`<button class="ico" type="button" data-act="up" data-l="${key}" data-i="${i}" title="${ar?"نقل لأعلى":"Move up"}">↑</button>`:"")
      +(i<rows.length-1?`<button class="ico" type="button" data-act="down" data-l="${key}" data-i="${i}" title="${ar?"نقل لأسفل":"Move down"}">↓</button>`:"")
      +`<button class="ico del" type="button" data-act="del" data-l="${key}" data-i="${i}"${hasData?' data-confirm-row="1"':""} title="${ar?"حذف":"Remove"}">✕</button></td>`;
    return `<tr${cols[1]&&cols[1].ar?' class="has-ar"':""}>${cells}${totalCell}${ctl}</tr>`;
  }).join("");
  const grand=tot&&!tot.hideGrand&&rows.length?`<tfoot><tr class="lt-grand"><td colspan="${cols.length}">${ar?"الإجمالي الكلي":"Grand total"}</td><td class="lt-total">${money(rows.reduce((s,r)=>s+lineCalc(r),0))}${cur?` ${esc(curUi(cur))}`:""}</td><td></td></tr></tfoot>`:"";
  return `<div class="lt-wrap"><table class="lt cardable"${minw?` style="min-width:${minw}"`:""}><thead><tr>${head}</tr></thead><tbody>${body}</tbody>${grand}</table></div>
  <button class="btn small addrow" type="button" data-act="add" data-l="${key}">+ ${addLabel}</button>`;
}
function cardEd(key,fields,title,addLabel){
  const rows=S[CUR_DOC][key]||(S[CUR_DOC][key]=[]);
  return rows.map((r,i)=>`<div class="card"><div class="card-h"><span>${title(r,i)}</span><span><button class="ico" type="button" data-act="up" data-l="${key}" data-i="${i}" title="Move up">↑</button><button class="ico del" type="button" data-act="del" data-l="${key}" data-i="${i}" title="Remove">✕</button></span></div><div class="grid one">${fields.map(f=>`<div class="f"><label>${f.label}</label>${f.area?`<textarea class="in" rows="${f.area}" data-l="${key}" data-i="${i}" data-c="${f.c}"${f.ar?' dir="rtl"':""}>${esc(r[f.c])}</textarea>`:`<input class="in" data-l="${key}" data-i="${i}" data-c="${f.c}" value="${esc(r[f.c])}"${f.ar?' dir="rtl"':""}>`}</div>`).join("")}</div></div>`).join("")+`<button class="btn small" type="button" data-act="add" data-l="${key}">+ ${addLabel}</button>`;
}
const CURS=Object.keys(CUR).map(k=>[k,`${k} — ${UI_AR&&CUR[k].ar?CUR[k].ar:CUR[k].en}`]);
const SEXO=Object.entries(SEX).map(([k,v])=>[k,`${v[0]} / ${v[1]}`]);
const pedFld=p=>fld("p_"+p,pedLabel(p,UI_AR));
// one side's 4th and 5th generation (8 + 16 ancestors), after the parents' three generations
const pedDeep=side=>{let g=[side+"s",side+"d"];g=g.flatMap(p=>[p+"s",p+"d"]);const g4=g.flatMap(p=>[p+"s",p+"d"]);return g4.concat(g4.flatMap(p=>[p+"s",p+"d"]))};
function feedEd(){const d=S.diet,sl=d.slots||[];return listEd("items",[{c:"name",label:"Feed",w:"170px"},...sl.map((s,i)=>({c:"s"+i,label:esc(UI_AR&&s.ar?s.ar:s.en),w:"96px"}))],"Add feed","760px")+`<p class="hint" style="margin-top:8px">Filled from the horse's diet plan in the Horses module. Leave the grid empty to print a blank sheet for the grooms.</p>`}

/* ---------- Financial Report (bills-backed draft) ---------- */
const FIN_STATUS_OPTS=[["draft","Draft"],["pending","Pending"],["approved","Approved"],["partially_paid","Partially paid"],["paid","Paid"],["overdue","Overdue"],["cancelled","Cancelled"]];
const FIN_TYPE_OPTS=[["expense","Expense"],["income","Income"],["liability","Liability"]];
const FIN_STATUS_LABEL=Object.fromEntries(FIN_STATUS_OPTS);
function finBasis(kind,r){const rem=Math.max(0,n2(r.amt)-n2(r.paid));return kind==="outstanding"?rem:n2(r.amt)}
function finLastDay(y,m){return new Date(y,m,0).getDate()}
function finMonthRange(ym){const m=/^(\d{4})-(\d{2})$/.exec(ym||"");if(!m)return["",""];const y=+m[1],mo=+m[2];return [`${ym}-01`,`${ym}-${String(finLastDay(y,mo)).padStart(2,"0")}`]}
/* Payee/name comes first (not an index number) so the mobile "card" layout (.lt.cardable, same generic
 * CSS every other line-items grid already uses) picks a sensible full-width title for each card; every
 * cell carries data-label for that same mobile view, same convention as listEd()'s own generated cells. */
function finRowHtml(d,r,i){
  const ar=d.lang==="ar",rem=Math.max(0,n2(r.amt)-n2(r.paid)),last=d.rows.length-1;
  const ctl=`<td class="ctl">`
    +(i>0?`<button class="ico" type="button" data-fin="up" data-i="${i}" title="Move up">↑</button>`:"")
    +(i<last?`<button class="ico" type="button" data-fin="down" data-i="${i}" title="Move down">↓</button>`:"")
    +`<button class="ico del" type="button" data-fin="remove" data-line="${r.lineId}" title="Remove">✕</button></td>`;
  if(r.isManual){
    return `<tr class="fin-manual">
     <td data-label="Name" class="fin-name"><span class="manual-tag">Manual</span>
         <input class="in small" data-fin-m="${i}" data-fc="en" value="${esc(r.en)}" placeholder="Name (English)">
         <input class="in small" data-fin-m="${i}" data-fc="ar" value="${esc(r.ar)}" placeholder="الاسم (عربي)" dir="rtl">
         <input class="in small" data-fin-m="${i}" data-fc="desc" value="${esc(r.desc)}" placeholder="Description (optional)"></td>
     <td data-label="Bill / Date">—</td>
     <td data-label="Type"><select data-fin-m="${i}" data-fc="type">${FIN_TYPE_OPTS.map(([a,b])=>`<option value="${a}"${r.type===a?" selected":""}>${b}</option>`).join("")}</select></td>
     <td data-label="Amount" class="lt-money"><input class="in" data-fin-m="${i}" data-fc="amt" data-money="1" inputmode="decimal" dir="ltr" value="${money(r.amt)}"></td>
     <td data-label="Paid" class="lt-money"><input class="in" data-fin-m="${i}" data-fc="paid" data-money="1" inputmode="decimal" dir="ltr" value="${money(r.paid)}"></td>
     <td data-label="Remaining" class="num r">${money(rem)}</td>
     <td data-label="Status"><select data-fin-m="${i}" data-fc="status">${FIN_STATUS_OPTS.map(([a,b])=>`<option value="${a}"${r.status===a?" selected":""}>${b}</option>`).join("")}</select></td>
     ${ctl}</tr>`;
  }
  const payee=ar?(r.partyAr||r.partyEn):(r.partyEn||r.partyAr);
  const cat=ar?(r.catAr||r.catEn):(r.catEn||r.catAr);
  return `<tr class="fin-bill${r.status==="overdue"?" is-overdue":""}${r.status==="cancelled"?" is-cancelled":""}">
   <td data-label="Payee / Category">${esc(payee||"—")}${cat?`<small>${esc(cat)}</small>`:""}${r.desc?`<small>${esc(r.desc)}</small>`:""}</td>
   <td data-label="Bill / Date"><span class="nowrap">${esc(r.number||"—")}</span>${r.date?`<small>${esc(fmtDate(r.date,UI_LANG))}</small>`:""}</td>
   <td data-label="Type">${esc(trS(FIN_TYPE_OPTS.find(x=>x[0]===r.type)?.[1]||r.type||""))}</td>
   <td data-label="Amount" class="num r">${money(r.amt)}${r.currency&&r.currency!=="QAR"?`<small>${money(r.amtOriginal!=null?r.amtOriginal:r.amt)} ${esc(r.currency)}</small>`:""}</td>
   <td data-label="Paid" class="num r">${money(r.paid)}</td>
   <td data-label="Remaining" class="num r">${money(rem)}</td>
   <td data-label="Status"><span class="pill">${esc(FIN_STATUS_LABEL[r.status]||r.status||"")}</span>${r.due?`<small>${esc((UI_AR?"الاستحقاق ":"Due ")+fmtDate(r.due,UI_LANG))}</small>`:""}</td>
   ${ctl}</tr>`;
}
const ED={};
ED.fin=()=>{
  const d=S.fin;
  if(CFG.mode==="template"){
    return `<div class="ed-h"><h2>Financial Report — template defaults</h2></div><p class="hint">Reports are now built by picking real bills from Finance, so only these starting defaults are configurable here — not a fixed list of lines.</p>`+
     sec("Defaults",fld("reportKind","Default report type",{opts:[["outstanding","Outstanding payables"],["expenses","Expenses report"],["income","Income report"],["full","Full monthly statement"]]})+
       fld("groupBy","Default grouping",{opts:[["none","No grouping"],["category","By category"],["party","By supplier / payee"],["type","By type"]]})+
       fld("titleEn","Title (English)",{wide:1})+fld("titleAr","Title (Arabic)",{wide:1,ar:1})+fld("subEn","Subtitle (English)",{wide:1})+fld("subAr","Subtitle (Arabic)",{wide:1,ar:1}))+
     sec("Signatures",fld("prepared","Prepared by (name)")+fld("approved","Approved by (name)"));
  }
  const kind=d.reportKind||"outstanding",rows=d.rows||(d.rows=[]);
  const total=rows.filter(r=>r.status!=="cancelled").reduce((s,r)=>s+finBasis(kind,r),0);
  const head=`<tr><th>Payee / Category</th><th style="width:100px">Bill / Date</th><th style="width:84px">Type</th><th style="width:108px">Amount</th><th style="width:96px">Paid</th><th style="width:100px">Remaining</th><th style="width:150px">Status</th><th></th></tr>`;
  const body=rows.map((r,i)=>finRowHtml(d,r,i)).join("");
  return `<div class="ed-h"><h2>Financial Report</h2>${langSeg()}</div><p class="hint">Lines come from Finance → Bills &amp; transactions. Pick the bills for this report below, or add a manual line for anything that isn't a bill yet.</p>`+
   sec("Report",
     fld("reportKind","Report type",{opts:[["outstanding","Outstanding payables"],["expenses","Expenses report"],["income","Income report"],["full","Full monthly statement"]]})+
     fld("groupBy","Group lines by",{opts:[["none","No grouping"],["category","Category"],["party","Supplier / payee"],["type","Type"]]})+
     `<div class="f"><label>Period</label><div class="seg" role="group"><button type="button" data-fin="periodMonth" aria-pressed="${d.periodMode!=="custom"}">Month</button><button type="button" data-fin="periodCustom" aria-pressed="${d.periodMode==="custom"}">Custom range</button></div></div>`+
     (d.periodMode==="custom"?fld("periodFrom","From",{type:"date"})+fld("periodTo","To",{type:"date"}):fld("periodMonth","Month",{type:"month"}))+
     fld("titleEn","Title (English)",{wide:1})+fld("titleAr","Title (Arabic)",{wide:1,ar:1})+fld("subEn","Subtitle (English)",{wide:1})+fld("subAr","Subtitle (Arabic)",{wide:1,ar:1}))+
   `<div class="sec fin-lines"><h3>Line items</h3>
    <div class="fin-toolbar">
     <button type="button" class="btn small" data-fin="addBills">+ ${T.finAddBills||"Add bills"}</button>
     <button type="button" class="btn small" data-fin="monthly">${T.finMonthlyReport||"Monthly report"}</button>
     <button type="button" class="btn small" data-fin="addManual">+ Add manual line</button>
     <button type="button" class="btn small" data-fin="refresh">⟳ ${T.finRefresh||"Refresh from bills"}</button>
     <select data-fin="sortBy"><option value="">Sort by…</option><option value="date">Date</option><option value="supplier">Supplier / payee</option><option value="amount">Amount</option><option value="category">Category</option></select>
    </div>
    <div class="lt-wrap"><table class="lt cardable fin-lt" style="min-width:860px"><thead>${head}</thead><tbody>${body||'<tr><td colspan="8" class="fin-empty">No bills added yet — use “Add bills” or “Monthly report” above.</td></tr>'}</tbody></table></div>
    <div class="sum">Lines <b>${rows.length}</b> Total <b>${money(total)} ${curUi("QAR")}</b></div></div>`+
   sec("Signatures",fld("prepared","Prepared by (name)")+fld("approved","Approved by (name)"));
};
ED.diet=()=>`<div class="ed-h"><h2>Diet Log</h2>${langSeg()}</div><p class="hint">Fill in the horse details. Leave the feed grid empty to print a blank sheet for the grooms, or enter amounts to print a filled plan.</p>`+
 sec("Horse",fld("horse","Horse name")+fld("dob","Date of birth")+fld("sire","Sire")+fld("dam","Dam")+fld("preg","Pregnancy status (English)")+fld("pregAr","Pregnancy status (Arabic)",{ar:1})+fld("box","Stable / box no.")+fld("working","Working / in training",{opts:[["","Not set"],["yes","Yes"],["no","No"]]})+fld("work","Work details (English)")+fld("workAr","Work details (Arabic)",{ar:1})+fld("date","Log date (optional)",{type:"date"}))+
 `<div class="sec"><h3>Feed plan</h3>${feedEd()}</div>`+
 sec("Notes & sign-off",fld("notes","Notes / instructions (English)",{area:3,wide:1})+fld("notesAr","Notes / instructions (Arabic)",{area:3,wide:1,ar:1})+fld("recorded","Recorded by")+fld("checked","Checked by"));
ED.po=()=>{const d=S.po,sub=d.items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0);return `<div class="ed-h"><h2>Purchase Order</h2>${langSeg()}</div><p class="hint">Line totals, the grand total and the amount in words are calculated automatically. The sample items are placeholders.</p>`+
 sec("Order",fld("no","PO number")+fld("date","PO date",{type:"date"})+fld("delivery","Delivery date",{type:"date"})+fld("currency","Currency",{opts:CURS})+fld("ref","Quotation / reference (optional)",{wide:1}))+
 sec("Supplier",fld("supplier","Supplier name (English)")+fld("supplierAr","Supplier name (Arabic)",{ar:1})+fld("contact","Contact person")+fld("phone","Phone")+fld("email","Email",{wide:1}))+
 sec("Delivery & payment",fld("shipTo","Deliver to (English)")+fld("shipToAr","Deliver to (Arabic)",{ar:1})+fld("payment","Payment terms (English)")+fld("paymentAr","Payment terms (Arabic)",{ar:1}))+
 `<div class="sec"><h3>Items</h3>${listEd("items",[{c:"d",label:"Description",w:"220px"},{c:"dAr",label:"Description (Arabic)",ar:1,w:"200px"},{c:"q",label:"Qty",num:1,w:"64px"},{c:"u",label:"Unit",w:"76px"},{c:"p",label:"Unit price",money:1,w:"110px"}],"Add item",null,{total:{qty:"q",price:"p",currency:"currency",hideGrand:1}})}
 <div class="sum">Subtotal <b>${money(sub)} ${esc(curUi(d.currency))}</b> Total <b>${money(sub-n2(d.discount))} ${esc(curUi(d.currency))}</b></div></div>`+
 sec("Totals & terms",fld("discount","Discount amount",{type:"number"})+`<div></div>`+fld("terms","Terms & notes (English)",{area:4,wide:1})+fld("termsAr","Terms & notes (Arabic)",{area:4,wide:1,ar:1})+fld("requested","Requested by")+fld("approved","Approved by"))};
ED.sal=()=>`<div class="ed-h"><h2>Salary Certificate</h2></div><p class="hint">Prints English and Arabic side by side. The salary in words is written for you in both languages.</p>`+
 sec("Certificate",fld("date","Date",{type:"date"})+fld("gender","Employee",{opts:[["m","Male (Mr. / السيد)"],["f","Female (Ms. / السيدة)"]]})+fld("toEn","Addressed to (English)")+fld("toAr","Addressed to (Arabic)",{ar:1})+fld("cityEn","City (English)")+fld("cityAr","City (Arabic)",{ar:1}))+
 sec("Employee",fld("nameEn","Full name (English)")+fld("nameAr","Full name (Arabic)",{ar:1})+fld("natEn","Nationality (English)",{ph:"Lebanese"})+fld("natAr","Nationality (Arabic)",{ar:1,ph:"لبناني"})+fld("qid","Qatar ID no.")+fld("qidExp","QID valid until",{ph:"08/09/2027"})+fld("posEn","Position (English)")+fld("posAr","Position (Arabic)",{ar:1})+fld("sinceEn","Employed since (English)",{ph:"February 2026"})+fld("sinceAr","Employed since (Arabic)",{ar:1,ph:"فبراير 2026"}))+
 sec("Salary & signatory",fld("salary","Monthly salary (QAR)",{type:"number"})+fld("signName","Authorized signatory"));
ED.offer=()=>`<div class="ed-h"><h2>Employment Offer</h2>${langSeg()}</div><p class="hint">A formal offer letter with clauses and a salary table. Fill English and Arabic to print either.</p>`+
 sec("Letter",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+fld("tableAfter","Salary table after clause no.",{type:"number"})+fld("gender","Candidate",{opts:[["f","Female"],["m","Male"]]}))+
 sec("Candidate",fld("honEn","Title (English)",{ph:"Ms."})+fld("honAr","Title (Arabic)",{ar:1,ph:"السيدة"})+fld("nameEn","Name (English)")+fld("nameAr","Name (Arabic)",{ar:1})+fld("natEn","Nationality (English)")+fld("natAr","Nationality (Arabic)",{ar:1})+fld("posEn","Position (English)")+fld("posAr","Position (Arabic)",{ar:1})+fld("passport","Passport no.",{wide:1}))+
 sec("Salary",fld("salAmt","Monthly salary",{type:"number"})+fld("salCur","Currency",{opts:CURS})+fld("salQar","Equivalent in QAR",{type:"number",wide:1}))+
 sec("Opening",fld("greetEn","Greeting (English)",{wide:1})+fld("greetAr","Greeting (Arabic)",{wide:1,ar:1})+fld("introEn","Introduction (English)",{area:4,wide:1})+fld("introAr","Introduction (Arabic)",{area:4,wide:1,ar:1}))+
 `<div class="sec"><h3>Clauses</h3><p class="hint">In any text you can use {name}, {hon}, {position}, {owner}, {salary}, {salaryWords} and {salaryQar}. Wrap words in **double stars** for bold, and start a line with “1.” for a numbered list.</p>${cardEd("clauses",[{c:"tEn",label:"Title (English)"},{c:"bEn",label:"Text (English)",area:4},{c:"tAr",label:"Title (Arabic)",ar:1},{c:"bAr",label:"Text (Arabic)",area:4,ar:1}],(r,i)=>`Clause ${i+1} · ${esc(r.tEn||"Untitled")}`,"Add clause")}</div>`+
 `<div class="sec"><h3>Salary table rows</h3>${cardEd("money",[{c:"elEn",label:"Element (English)"},{c:"amEn",label:"Amount (English) — second line is the small note",area:2},{c:"ptEn",label:"Payment terms (English)"},{c:"elAr",label:"Element (Arabic)",ar:1},{c:"amAr",label:"Amount (Arabic)",area:2,ar:1},{c:"ptAr",label:"Payment terms (Arabic)",ar:1}],(r,i)=>`Row ${i+1} · ${esc(r.elEn||"")}`,"Add row")}</div>`+
 sec("Employer signatory",fld("ownerEn","Full name (English)")+fld("ownerAr","Full name (Arabic)",{ar:1})+fld("ownerShortEn","Short name in clauses (English)")+fld("ownerShortAr","Short name in clauses (Arabic)",{ar:1})+fld("ownerTitleEn","Title (English)")+fld("ownerTitleAr","Title (Arabic)",{ar:1})+fld("ownerQid","QID no.",{wide:1}));
ED.inv=()=>{const d=S.inv,isR=d.mode==="receipt";const sub=d.items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0);
 return `<div class="ed-h"><h2>Invoice / Receipt</h2>${langSeg()}</div><p class="hint">Invoice clients (for example boarding) and print a receipt voucher when they pay. Amounts in words are written for you.</p>`+
 `<div class="sec"><h3>Document type</h3><div class="seg" role="group" aria-label="Document type"><button type="button" data-mode="invoice" aria-pressed="${!isR}">Invoice</button><button type="button" data-mode="receipt" aria-pressed="${isR}">Receipt voucher</button></div></div>`+
 (isR?sec("Receipt",fld("rno","Receipt no.")+fld("date","Date",{type:"date"})+fld("currency","Currency",{opts:CURS})+fld("rAmount","Amount received",{type:"number"}))
     :sec("Invoice",fld("no","Invoice no.")+fld("date","Invoice date",{type:"date"})+fld("due","Due date",{type:"date"})+fld("currency","Currency",{opts:CURS})))+
 sec(isR?"Received from":"Customer",fld("cName","Name (English)")+fld("cNameAr","Name (Arabic)",{ar:1})+fld("cPhone","Phone")+fld("cEmail","Email")+(isR?"":fld("cAddr","Address (English)")+fld("cAddrAr","Address (Arabic)",{ar:1})))+
 (isR?sec("Payment",fld("rFor","Being payment for (English)",{wide:1,ph:"e.g. Invoice SK/INV/2026/001 – boarding October"})+fld("rForAr","Being payment for (Arabic)",{wide:1,ar:1})+fld("rMethod","Payment method",{opts:[["cash","Cash"],["cheque","Cheque"],["transfer","Bank transfer"],["card","Card"]]})+fld("rRef","Cheque / reference no.")+fld("rBank","Bank")+fld("receivedBy","Received by"))+`<div class="sec"><button class="btn small" type="button" data-act="useInvTotal">Use the invoice total (${money(sub-n2(d.discount)-n2(d.paid))} ${esc(curUi(d.currency))})</button></div>`
     :`<div class="sec"><h3>Items</h3>${listEd("items",[{c:"d",label:"Description",w:"240px"},{c:"dAr",label:"Description (Arabic)",ar:1,w:"200px"},{c:"q",label:"Qty",num:1,w:"64px"},{c:"p",label:"Unit price",money:1,w:"120px"}],"Add item",null,{total:{qty:"q",price:"p",currency:"currency",hideGrand:1}})}<div class="sum">Subtotal <b>${money(sub)} ${esc(curUi(d.currency))}</b> Balance due <b>${money(sub-n2(d.discount)-n2(d.paid))} ${esc(curUi(d.currency))}</b></div></div>`+
      sec("Totals & payment",fld("discount","Discount",{type:"number"})+fld("paid","Already paid",{type:"number"})+fld("payInfo","Payment details (English)",{area:2,wide:1})+fld("payInfoAr","Payment details (Arabic)",{area:2,wide:1,ar:1})))};
ED.pay=()=>{const d=S.pay,tot=d.staff.reduce((s,r)=>s+payNet(r).net,0);
 return `<div class="ed-h"><h2>Payslips</h2>${langSeg()}</div><p class="hint">Payslips come from the approved payroll. Each payslip is filled from its payroll line; open a payroll run to print all payslips of the month at once, two per page.</p>`+
 sec("Month",fld("month","Month",{type:"month"})+fld("payDate","Pay date",{type:"date"})+fld("method","Payment method")+fld("summary","Print payroll summary page",{opts:[["yes","Yes"],["no","No"]]})+fld("prepared","Prepared by",{wide:1}))+
 `<div class="sec"><h3>Staff</h3>${listEd("staff",[{c:"name",label:"Name (English)",w:"170px"},{c:"nameAr",label:"Name (Arabic)",ar:1,w:"150px"},{c:"pos",label:"Position",w:"140px"},{c:"posAr",label:"Position (Arabic)",ar:1,w:"130px"},{c:"basic",label:"Basic",money:1,w:"100px"},{c:"allow",label:"Allowances",money:1,w:"100px"},{c:"ot",label:"Overtime / bonus",money:1,w:"100px"},{c:"ded",label:"Deductions",money:1,w:"100px"},{c:"adv",label:"Advance",money:1,w:"100px"}],"Add employee","1250px",{total:{calc:r=>payNet(r).net,label:"Net",hideGrand:1}})}
 <div class="quick" style="margin-top:8px"><button class="btn small" type="button" data-act="payClear">Clear overtime, deductions & advances</button></div>
 <div class="sum">Staff <b>${d.staff.length}</b> Total net pay <b>${money(tot)} ${curUi("QAR")}</b></div></div>`};
ED.vet=()=>{const d=S.vet,up=vetUpcoming(d),ST={over:"Overdue",soon:"Due soon",ok:"On schedule"};
 return `<div class="ed-h"><h2>Vet Record</h2>${langSeg()}</div><p class="hint">Log vaccinations, deworming, farrier and vet visits for each horse. Add the next due date and the record shows what is overdue or coming up.</p>`+
 sec("Horse",fld("horse","Horse name",{wide:1})+fld("sex","Sex",{opts:SEXO})+fld("dob","Date of birth")+fld("sire","Sire")+fld("dam","Dam")+fld("chip","Microchip no.")+fld("box","Stable / box no."))+
 (up.length?`<div class="sec"><h3>Coming up</h3><div class="upc">${up.map(e=>{const st=vetStatus(e.next);return `<div><span><b>${OL(VT[e.type])}</b> · ${fmtDate(e.next,UI_LANG)}</span>${st?`<span class="st ${st}">${ST[st]}</span>`:""}</div>`}).join("")}</div></div>`:"")+
 `<div class="sec"><h3>Treatments</h3>${listEd("entries",[{c:"date",label:"Date",date:1,w:"150px"},{c:"type",label:"Type",opts:Object.entries(VT).map(([k,v])=>[k,OL(v)]),w:"130px"},{c:"what",label:"Details",w:"200px"},{c:"whatAr",label:"Details (Arabic)",ar:1,w:"180px"},{c:"by",label:"By",w:"110px"},{c:"next",label:"Next due",date:1,w:"150px"}],"Add treatment","900px")}</div>`+
 sec("Notes",fld("notes","Notes (English)",{area:3,wide:1})+fld("notesAr","Notes (Arabic)",{area:3,wide:1,ar:1}))};
ED.cover=()=>{const d=S.cover,fd=foalDate(d);return `<div class="ed-h"><h2>Covering Certificate</h2>${langSeg()}</div><p class="hint">Record which stallion covered a mare and when. The expected foaling date is worked out from the last covering date.</p>`+
 sec("Certificate",fld("no","Certificate no.")+fld("date","Date",{type:"date"})+fld("season","Season"))+
 sec("Mare",fld("mare","Mare name",{wide:1})+fld("mareDob","Date of birth")+fld("mareReg","Passport / reg. no.")+fld("mareSire","Sire")+fld("mareDam","Dam")+fld("mareChip","Microchip no.",{wide:1}))+
 sec("Mare owner",fld("ownerEn","Owner (English)")+fld("ownerAr","Owner (Arabic)",{ar:1})+fld("ownerPhone","Phone",{wide:1}))+
 sec("Stallion",fld("stallion","Stallion name",{wide:1})+fld("stallionReg","Passport / reg. no.",{wide:1})+fld("stallionSire","Sire")+fld("stallionDam","Dam")+fld("stOwnerEn","Owner (English)")+fld("stOwnerAr","Owner (Arabic)",{ar:1}))+
 `<div class="sec"><h3>Covering</h3><div class="grid">${fld("method","Method",{opts:Object.entries(METH).map(([k,v])=>[k,OL(v)])})+fld("gest","Gestation days",{type:"number"})}</div><div style="margin-top:12px">${listEd("covers",[{c:"date",label:"Covering date",date:1,w:"200px"}],"Add covering date","0")}</div>${fd?`<div class="calc"><div class="t"><span>Expected foaling</span><span>${fmtDate(fd,UI_LANG)}</span></div></div>`:""}</div>`+
 sec("Pregnancy check",fld("checkResult","Result",{opts:Object.entries(PREG).map(([k,v])=>[k,OL(v)])})+fld("checkDate","Check date",{type:"date"})+fld("vet","Veterinarian",{wide:1})+fld("notes","Notes (English)",{area:2,wide:1})+fld("notesAr","Notes (Arabic)",{area:2,wide:1,ar:1}))};
ED.board=()=>{const d=S.board,n=(d.horses||[]).filter(h=>(h.name||"").trim()).length||1;return `<div class="ed-h"><h2>Boarding Agreement</h2>${langSeg()}</div><p class="hint">For owners who keep their horses at your stud. The end date and notice deadline are worked out for you and show up in Reminders.</p>`+
 sec("Agreement",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+fld("start","Start date",{type:"date"})+fld("term","Term (months)",{type:"number"}))+
 sec("Owner",fld("ownerEn","Name (English)")+fld("ownerAr","Name (Arabic)",{ar:1})+fld("ownerId","ID / C.R. no.")+fld("ownerPhone","Phone"))+
 `<div class="sec"><h3>Horses</h3>${listEd("horses",[{c:"name",label:"Horse name",w:"220px"},{c:"sex",label:"Sex",opts:SEXO,w:"170px"},{c:"box",label:"Box no.",w:"90px"}],"Add horse")}</div>`+
 sec("Fees",fld("fee","Monthly fee per horse",{type:"number"})+fld("currency","Currency",{opts:CURS})+fld("dueDay","Payment due (day of month)",{type:"number"})+fld("deposit","Deposit (0 = none)",{type:"number"})+fld("notice","Notice period (days)",{type:"number",wide:1}))+
 `<div class="calc"><div><span>End date</span><span>${fmtDate(boardEnd(d),UI_LANG)||"—"}</span></div><div class="t"><span>Total per month (${n} horse${n>1?"s":""})</span><span>${money(n2(d.fee)*n)} ${esc(curUi(d.currency))}</span></div></div>`+
 `<div class="sec"><h3>Included in the fee</h3><div class="chks">${Object.entries(INC).map(([k,v])=>`<label><input type="checkbox" data-inc="${k}"${d.inc&&d.inc[k]?" checked":""}>${OL(v)}</label>`).join("")}</div><div class="grid one" style="margin-top:12px">${fld("extrasEn","Charged separately (English)",{area:2})+fld("extrasAr","Charged separately (Arabic)",{area:2,ar:1})}</div></div>`+
 `<div class="sec"><h3>Terms</h3><p class="hint">You can use {start}, {end}, {term}, {fee}, {deposit}, {notice} and {dueDay}. The deposit clause is left out when the deposit is 0.</p>${cardEd("clauses",[{c:"tEn",label:"Title (English)"},{c:"bEn",label:"Text (English)",area:3},{c:"tAr",label:"Title (Arabic)",ar:1},{c:"bAr",label:"Text (Arabic)",area:3,ar:1}],(r,i)=>`Term ${i+1} · ${esc(r.tEn||"")}`,"Add term")}</div>`};
ED.letters=()=>{const d=S.letters,t=d.type;const seg=`<div class="sec"><h3>Letter type</h3><div class="seg" role="group" aria-label="Letter type" style="flex-wrap:wrap">${Object.entries(LTYPE).map(([k,v])=>`<button type="button" data-ltype="${k}" aria-pressed="${t===k}">${OL(v)}</button>`).join("")}</div></div>`;
 const bil=t==="exp"||t==="noc";
 let h=`<div class="ed-h"><h2>Staff Letters</h2>${bil?"":langSeg()}</div><p class="hint">${bil?"Prints English and Arabic side by side.":"Prints in English or Arabic."} Choose the employee above and their details are filled in from the Employees module.</p>`+seg+
  sec("Letter",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+(bil?fld("signName","Authorized signatory",{wide:1}):""))+
  sec("Employee",fld("gender","Employee",{opts:[["m","Male (Mr. / السيد)"],["f","Female (Ms. / السيدة)"]]})+fld("qid","Qatar ID no.")+fld("nameEn","Name (English)")+fld("nameAr","Name (Arabic)",{ar:1})+fld("natEn","Nationality (English)")+fld("natAr","Nationality (Arabic)",{ar:1})+fld("posEn","Position (English)")+fld("posAr","Position (Arabic)",{ar:1})+fld("joinDate","Joining date",{type:"date"})+fld("qidExp","QID valid until",{ph:"dd/mm/yyyy"}));
 if(t==="exp")h+=sec("Service",fld("endDate","Last working day (leave empty if still employed)",{type:"date",wide:1})+fld("toEn","Addressed to (English, optional)")+fld("toAr","Addressed to (Arabic, optional)",{ar:1}));
 if(t==="noc")h+=sec("Purpose",fld("toEn","Addressed to (English)")+fld("toAr","Addressed to (Arabic)",{ar:1})+fld("purposeEn","No objection to him/her… (English)",{wide:1})+fld("purposeAr","ولا مانع لدى الشركة من… (Arabic)",{wide:1,ar:1}));
 if(t==="leave"){const days=d.from&&d.to&&d.to>=d.from?dayDiff(d.from,d.to)+1:0;h+=sec("Leave",fld("leaveType","Type of leave",{opts:Object.entries(LEAVE).map(([k,v])=>[k,OL(v)])})+`<div></div>`+fld("from","From",{type:"date"})+fld("to","To",{type:"date"})+fld("reason","Reason (English)")+fld("reasonAr","Reason (Arabic)",{ar:1})+fld("replacement","Replacement during leave")+fld("contact","Contact during leave"))+(days?`<div class="calc"><div><span>Number of days</span><span>${days}</span></div><div class="t"><span>Back to work</span><span>${fmtDate(addDays(d.to,1),UI_LANG)}</span></div></div>`:"")}
 if(t==="eos"){const c=eosCalc(d);h+=sec("Settlement",fld("endDate","Last working day",{type:"date"})+fld("eosReason","Reason",{opts:Object.entries(EOSR).map(([k,v])=>[k,OL(v)])})+fld("basic","Basic monthly salary (QAR)",{type:"number"})+fld("dpy","Gratuity days per year",{type:"number"})+fld("leaveDays","Leave balance (days)",{type:"number"})+fld("unpaid","Unpaid salary",{type:"number"})+fld("other","Other dues",{type:"number"})+fld("deductions","Deductions & advances",{type:"number"})+fld("signName","Signed for the company by",{wide:1}))+
   `<div class="calc"><div><span>Service</span><span>${lenText(c.len,UI_AR)}</span></div><div><span>Gratuity</span><span>${money(c.grat)} ${curUi("QAR")}</span></div><div><span>Leave balance</span><span>${money(c.leave)} ${curUi("QAR")}</span></div><div class="t"><span>Net payable</span><span>${money(c.total)} ${curUi("QAR")}</span></div></div><p class="hint" style="margin-top:8px">Uses ${+d.dpy||21} days of basic salary per year of service (the Qatar Labour Law minimum is three weeks), nothing for under one year. Check the employee's contract for better terms.</p>`}
 return h};
/* Horse Profile → "What to print": the parts of the paper, each ticked unless left out (S.profile.hide) */
const PFPARTS=[["details","Horse details"],["photo","Photo"],["pedigree","Pedigree"],["shows","Show results"],["progeny","Progeny"],["plan","Breeding program"],["covers","Breeding history"],["embryos","Embryos"],["health","Health record"],["notes","About this horse"]];
const PFBASIC=["details","photo","pedigree"];
const partsEd=d=>{const hid=new Set(Array.isArray(d.hide)?d.hide:[]);return `<div class="sec"><h3>What to print</h3><p class="hint">Untick the parts this paper should leave out.</p><div class="quick"><button type="button" class="btn small" data-act="pfAll">Everything</button><button type="button" class="btn small" data-act="pfBasic">Details and pedigree only</button></div><div class="pf-parts">${PFPARTS.map(([k,l])=>`<label class="chk"><input type="checkbox" data-pfpart="${k}"${hid.has(k)?"":" checked"}> ${l}</label>`).join("")}</div></div>`};
ED.profile=()=>{const d=S.profile;return `<div class="ed-h"><h2>Horse Profile</h2>${langSeg()}</div><p class="hint">One paper per horse: photo, details, a 5-generation pedigree, show results and progeny. Choose the horse above and its details are filled in from the Horses module.</p>`+
 partsEd(d)+
 sec("Horse",fld("nameEn","Name (English)")+fld("nameAr","Name (Arabic)",{ar:1})+fld("sex","Sex",{opts:SEXO})+fld("dob","Date of birth",{ph:"e.g. Jan 29, 2021"})+fld("colourEn","Colour (English)")+fld("colourAr","Colour (Arabic)",{ar:1})+fld("breed","Breed (English)")+fld("breedAr","Breed (Arabic)",{ar:1})+fld("breeder","Breeder")+fld("owner","Owner")+fld("origin","Country of birth")+fld("height","Height",{ph:"e.g. 152 cm"})+fld("chip","Microchip no.")+fld("passport","Passport / reg. no.")+fld("status","Status",{opts:[["","Not set"],...HSTAT]})+fld("box","Stable / box no."))+
 `<div class="sec"><h3>Photo</h3><div class="ph-row">${d.photo?`<img class="ph-thumb" src="${d.photo}" alt="Horse photo">`:`<div class="ph-thumb ph-empty">No photo</div>`}<div class="quick" style="margin:0;flex-direction:column;align-items:flex-start">${d.photo?`<button type="button" class="btn small" data-act="photoDel">Remove photo</button>`:""}<span class="hint" style="margin:0">The main photo from the horse's profile is used. Change it on the horse page.</span></div></div></div>`+
 sec("Pedigree – sire side",pedFld("s")+`<div></div>`+pedFld("ss")+pedFld("sd")+["sss","ssd","sds","sdd"].map(pedFld).join(""))+
 sec("Pedigree – dam side",pedFld("d")+`<div></div>`+pedFld("ds")+pedFld("dd")+["dss","dsd","dds","ddd"].map(pedFld).join(""))+
 sec("Pedigree – sire side, 4th and 5th generation",pedDeep("s").map(pedFld).join(""))+
 sec("Pedigree – dam side, 4th and 5th generation",pedDeep("d").map(pedFld).join(""))+
 `<div class="sec"><h3>Show results & achievements</h3>${listEd("shows",[{c:"date",label:"Date",date:1,w:"150px"},{c:"show",label:"Show",w:"200px"},{c:"cls",label:"Class",w:"150px"},{c:"result",label:"Result",w:"130px"}],"Add result")}</div>`+
 `<div class="sec"><h3>Breeding program</h3><p class="hint">Planned and past matings. Once a mating is marked covered or in foal, the expected foaling date is worked out, and planned dates appear in Reminders.</p>${listEd("plan",[{c:"season",label:"Season",w:"80px"},{c:"partner",label:d.sex==="stallion"||d.sex==="colt"?"Mare":"Stallion",w:"180px"},{c:"method",label:"Method",opts:[["","Not stated"],...Object.entries(METH).map(([k,v])=>[k,OL(v)])],w:"160px"},{c:"date",label:"Date",date:1,w:"150px"},{c:"status",label:"Status",opts:Object.entries(PLANST).map(([k,v])=>[k,OL(v)]),w:"140px"},{c:"notes",label:"Notes",w:"180px"}],"Add mating")}</div>`+
 `<div class="sec"><h3>Embryos</h3><p class="hint">Embryos flushed or produced (ET / ICSI): where they are stored, which recipient mare carries them, and what happened.</p>${listEd("embryos",[{c:"date",label:"Date",date:1,w:"150px"},{c:"partner",label:d.sex==="stallion"||d.sex==="colt"?"Mare":"Sire",w:"170px"},{c:"method",label:"Method",opts:[["et","Embryo transfer"],["icsi","ICSI"]],w:"140px"},{c:"stage",label:"Stage",w:"120px"},{c:"grade",label:"Grade",w:"70px"},{c:"status",label:"Status",opts:Object.entries(EMBST).map(([k,v])=>[k,OL(v)]),w:"140px"},{c:"recipient",label:"Recipient mare",w:"150px"},{c:"storage",label:"Storage / tank",w:"130px"}],"Add embryo")}</div>`+
 `<div class="sec"><h3>Progeny</h3>${listEd("progeny",[{c:"name",label:"Name",w:"200px"},{c:"year",label:"Year",w:"80px"},{c:"sex",label:"Sex",opts:SEXO,w:"150px"},{c:"other",label:d.sex==="mare"||d.sex==="filly"?"Sire":"Dam",w:"180px"}],"Add foal")}</div>`+
 sec("About this horse",fld("notesEn","Description (English)",{area:4,wide:1})+fld("notesAr","Description (Arabic)",{area:4,wide:1,ar:1}))};
ED.idcard=()=>{const d=S.idcard,emps=(CFG.ctx&&CFG.ctx.employees)||[];if(!d.issue)d.issue=today();if(!d.expiry)d.expiry=addMonths(d.issue,12);if(!Array.isArray(d.pick))d.pick=[];
 return `<div class="ed-h"><h2>Staff ID Cards</h2></div><p class="hint">Bank-card size ID cards (85.6 × 54 mm, CR80) with photo. Print straight onto plastic cards with an ID card printer, or on A4 to cut out. Details and photos come from the Employees module.</p>`+
 sec("Card",fld("output","Print on",{opts:[["card","ID card printer (card-size pages: front, then back)"],["a4","A4 paper to cut out"]],wide:1})+fld("issue","Issue date",{type:"date"})+fld("expiry","Valid until",{type:"date"})+fld("title","Card title (English)")+fld("titleAr","Card title (Arabic)",{ar:1})+fld("showQid","Show Qatar ID on the back",{opts:[["yes","Yes"],["no","No"]]}))+
 `<div class="sec"><h3>Employees to print</h3><div class="quick"><button type="button" class="btn small" data-act="idAll">Tick all working staff</button><button type="button" class="btn small" data-act="idNone">Untick all</button></div>
 <div class="idlist">${emps.map(e=>{const on=d.pick.some(n=>low(n)===low(e.nameEn));return `<div class="idrow${on?" on":""}"><label class="idchk"><input type="checkbox" data-idpick="${esc(e.nameEn)}"${on?" checked":""}>${e.photo?`<img src="${esc(e.photo)}" alt="">`:`<span class="idnoph">?</span>`}<span><b>${esc(e.nameEn||"(no name)")}</b><small>${esc(e.posEn||"")} · ${esc(e.empNo||"")}${e.photo?"":" · <em>no photo</em>"}</small></span></label></div>`}).join("")||'<p class="hint">No employees to show.</p>'}</div></div>`};
ED.horses=()=>`<div class="ed-h"><h2>All Horses</h2></div><p class="hint">Every horse at the stud (${((CFG.ctx&&CFG.ctx.horses)||[]).length}), taken from the Horses module. Change a horse in Horses and this list follows.</p>`;
ED.staff=()=>`<div class="ed-h"><h2>All Employees</h2></div><p class="hint">Every working employee (${((CFG.ctx&&CFG.ctx.employees)||[]).length}), taken from the Employees module, with Qatar ID dates highlighted.</p>`;
ED.rem=()=>`<div class="ed-h"><h2>Reminders</h2></div><p class="hint">Everything coming up, gathered from staff documents, vet and farrier due dates, foalings and unpaid bills you are allowed to see.</p>`+
 sec("Show",fld("window","Look ahead",{opts:[["30","Next 30 days"],["60","Next 60 days"],["90","Next 90 days"],["180","Next 6 months"]]}),true);
ED.reg=()=>`<div class="ed-h"><h2>Document Register</h2></div><p class="hint">Every document issued in SK Arabian Studio with its reference and date. The full archive (open, re-print, void) is on the Studio page.</p>`+
 sec("Find",fld("filter","Document type",{opts:[["all","All documents"],...SK.types.filter(x=>x[0]!=="reg"&&x[0]!=="rem")]})+fld("status","Valid or void",{opts:[["all","All"],["valid","Valid only"],["void","Void only"]]})+fld("q","Search reference or name"));
ED.embryo=()=>`<div class="ed-h"><h2>Embryo Transfer Record</h2>${langSeg()}</div><p class="hint">Choose the embryo above: donor mare, sire, recipient, dates and pregnancy checks are filled in from the Embryos module.</p>`+
 sec("Record",fld("no","Record no.")+fld("date","Date",{type:"date"})+fld("code","Embryo no.")+fld("name","Name (optional)")+fld("flushDate","Flush date",{type:"date"})+fld("status","Status",{opts:Object.entries(EST).map(([k,v])=>[k,OL(v)])})+fld("grade","Grade")+fld("stage","Stage")+fld("storage","Storage location",{wide:1}))+
 sec("Owner",fld("ownerEn","Owner (English)")+fld("ownerAr","Owner (Arabic)",{ar:1}))+
 sec("Donor mare & sire",fld("donor","Donor mare")+fld("donorReg","Donor passport / reg. no.")+fld("donorSire","Donor's sire")+fld("donorDam","Donor's dam")+fld("sire","Sire")+fld("sireReg","Sire passport / reg. no."))+
 sec("Transfer",fld("recipient","Recipient mare")+fld("recipientReg","Recipient passport / reg. no.")+fld("transferDate","Transfer date",{type:"date"})+fld("expected","Expected foaling",{type:"date"})+fld("vet","Veterinarian",{wide:1}))+
 `<div class="sec"><h3>Pregnancy checks</h3>${listEd("checks",[{c:"date",label:"Date",date:1,w:"150px"},{c:"result",label:"Result",opts:Object.entries(CHK).map(([k,v])=>[k,OL(v)]),w:"170px"},{c:"notes",label:"Notes",w:"220px"}],"Add check")}</div>`+
 sec("Notes",fld("notes","Notes (English)",{area:3,wide:1})+fld("notesAr","Notes (Arabic)",{area:3,wide:1,ar:1}));
ED.letter=()=>`<div class="ed-h"><h2>Custom Letter</h2>${langSeg()}</div><p class="hint">A letter on the official letterhead. Wrap words in **double stars** for bold.</p>`+
 sec("Letter",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+fld("toEn","To (English)",{area:2})+fld("toAr","To (Arabic)",{area:2,ar:1})+fld("subjectEn","Subject (English)",{wide:1})+fld("subjectAr","Subject (Arabic)",{wide:1,ar:1}))+
 sec("Text",fld("bodyEn","Letter (English)",{area:10,wide:1})+fld("bodyAr","Letter (Arabic)",{area:10,wide:1,ar:1}))+
 sec("Signature",fld("signName","Signed by")+`<div></div>`+fld("signTitleEn","Title (English)")+fld("signTitleAr","Title (Arabic)",{ar:1}));

const NEWROW={embryos:()=>({date:today(),partner:"",method:"et",stage:"",grade:"",status:"frozen",recipient:"",storage:""}),plan:()=>({season:String(new Date().getFullYear()),partner:"",method:"natural",date:"",status:"planned",notes:""}),shows:()=>({date:"",show:"",cls:"",result:""}),progeny:()=>({name:"",year:"",sex:"filly",other:""}),covers:()=>({date:today()}),horses:()=>({name:"",sex:"mare",box:""}),clauses:()=>({tEn:"",bEn:"",tAr:"",bAr:""}),staff:()=>({name:"",nameAr:"",pos:"",posAr:"",basic:0,allow:0,ot:0,ded:0,adv:0}),entries:()=>({date:today(),type:"vacc",what:"",by:"",next:""}),rows:()=>({en:"",ar:"",qty:1,amt:0,type:"s",status:"pending"}),items:()=>CUR_DOC==="po"?{d:"",q:1,u:"",p:0}:CUR_DOC==="inv"?{d:"",q:1,p:0}:{name:"",s0:"",s1:"",s2:"",s3:"",s4:""},money:()=>({elEn:"",elAr:"",amEn:"",amAr:"",ptEn:"",ptAr:""}),checks:()=>({date:today(),result:"scheduled",notes:""})};

/* ---------- drawing ---------- */
const VIEW=CFG.mode==='view'||CFG.mode==='print';
function meta(){return{letterhead:LH,ref:CFG.ref||(CFG.numbered&&(CFG.mode==='new'||CFG.mode==='draft')?(T.refPending||''):''),issued:CFG.issued?fmtDate(CFG.issued,(S[CUR_DOC].lang==='ar'?'ar':'en')):'',issuedIso:CFG.issued||'',verifyUrl:CFG.verifyUrl||''}}
function context(){const c=Object.assign({},CFG.ctx||{});if(CUR_DOC==='rem')c.reminders=(c.reminders||[]).filter(x=>x.days<=(+S.rem.window||60));SK.setContext(c)}
/* The A4 preview always fits its panel ("Fit"); the zoom buttons step 50–150 % from there. */
let ZOOM=0; // 0 = fit
function fit(){if(!canvas||CFG.mode==='print')return;host.style.zoom=1;const pg=host.querySelector('.page');if(!pg)return;
  const avail=canvas.clientWidth-(window.innerWidth<900?24:48),fz=Math.min(1,avail/pg.offsetWidth),z=ZOOM||fz;
  host.style.zoom=z>0?z:1;const out=document.getElementById('zoomVal');if(out)out.textContent=Math.round((z>0?z:1)*100)+'%'}
function zoomBar(){if(!canvas||CFG.mode==='print'||document.getElementById('zoomVal'))return;
  const b=document.createElement('div');b.className='zoombar';b.setAttribute('role','group');b.setAttribute('aria-label',T.tabPreview||'Preview');
  b.innerHTML=`<button type="button" data-z="-" aria-label="${esc(T.zoomOut||'Zoom out')}" title="${esc(T.zoomOut||'Zoom out')}">−</button><span id="zoomVal" aria-live="polite">100%</span><button type="button" data-z="+" aria-label="${esc(T.zoomIn||'Zoom in')}" title="${esc(T.zoomIn||'Zoom in')}">+</button><button type="button" data-z="fit">${esc(T.zoomFit||'Fit')}</button>`;
  canvas.insertBefore(b,canvas.firstChild);
  b.addEventListener('click',e=>{const x=e.target.closest('[data-z]');if(!x)return;const cur=parseFloat(host.style.zoom)||1;
    if(x.dataset.z==='fit')ZOOM=0;else ZOOM=Math.max(.5,Math.min(1.5,Math.round((cur+(x.dataset.z==='+'?.1:-.1))*10)/10));fit()})}
/* Phones and tablets: a Form / Preview switch at the bottom (the CSS for it is in studio-ed.css) */
function viewTabs(){const root=document.querySelector('.studio-ed');if(!root||!canvas||!root.querySelector('.editor')||CFG.mode==='print'||root.querySelector('.mbar'))return;
  const first=VIEW?(T.tabDetails||'Details'):(T.tabForm||'Form');
  const bar=document.createElement('div');bar.className='mbar';
  bar.innerHTML=`<div class="mseg" role="group" aria-label="${esc(T.tabPreview||'Preview')}"><button type="button" data-view="form" aria-pressed="true">${esc(first)}</button><button type="button" data-view="preview" aria-pressed="false">${esc(T.tabPreview||'Preview')}</button></div>`;
  root.appendChild(bar);root.dataset.view='form';
  bar.addEventListener('click',e=>{const x=e.target.closest('[data-view]');if(!x)return;root.dataset.view=x.dataset.view;
    bar.querySelectorAll('[data-view]').forEach(y=>y.setAttribute('aria-pressed',String(y===x)));window.scrollTo(0,0);if(x.dataset.view==='preview')requestAnimationFrame(fit)})}
function drawPreview(){context();SK.render(host,CUR_DOC,S[CUR_DOC],meta());if(CFG.void)host.classList.add('doc-void');fit()}
function drawEditor(){if(!ed||!ED[CUR_DOC])return;const st=ed.scrollTop;ed.innerHTML=ED[CUR_DOC]();trUi(ed);ed.scrollTop=st;
  ed.querySelectorAll('[data-k="no"],[data-k="ref"],[data-k="rno"]').forEach(i=>{if(!i.value)i.placeholder=T.refPh||'';if(CFG.mode==='template'){i.disabled=true}});
  // (choosing what to print is not editing the record's data: it stays open to everyone who may issue the paper)
  if(!CFG.canEdit)ed.querySelectorAll('input:not([data-pfpart]),select,textarea,button:not([data-lang]):not([data-act^="pf"])').forEach(i=>{i.disabled=true});
  // ed.innerHTML just replaced every <select> with a fresh, unenhanced native one; the shared dropdown
  // component's own MutationObserver picks this up too, but calling it directly here is immediate and
  // doesn't depend on microtask timing for a part of the page that redraws this often.
  if(window.DD&&window.DD.scan)window.DD.scan(ed);}
let tm;function soon(){clearTimeout(tm);tm=setTimeout(drawPreview,160)}
function redraw(){drawEditor();drawPreview()}

/* ---------- editing (same behaviour as the original Studio) ---------- */
// A money input is addressed either the generic way (data-l/data-i/data-c, any listEd() grid) or, for a
// fin manual line, via data-fin-m/data-fc — both resolve to the same {row, key} shape for focusin/focusout.
function rowRef(t){return t.dataset.finM!==undefined?S.fin.rows[+t.dataset.finM]:S[CUR_DOC][t.dataset.l][+t.dataset.i]}
function colKey(t){return t.dataset.finM!==undefined?t.dataset.fc:t.dataset.c}
if(ed&&!VIEW){
  ed.addEventListener('input',e=>{const t=e.target,d=S[CUR_DOC];
    if(t.dataset.k){d[t.dataset.k]=t.type==="number"?(t.value===""?"":+t.value):t.value;
      if(CUR_DOC==="fin"&&t.dataset.k==="periodMonth"){const[pf,pt]=finMonthRange(d.periodMonth);d.periodFrom=pf;d.periodTo=pt}
      soon()}
    else if(t.dataset.l){const r=d[t.dataset.l][+t.dataset.i];
      r[t.dataset.c]=t.dataset.money?moneyParse(t.value):t.type==="number"?(t.value===""?"":+t.value):t.value;soon()}
    else if(t.dataset.finM!==undefined){const r=d.rows[+t.dataset.finM];if(!r)return;
      r[t.dataset.fc]=t.dataset.money?moneyParse(t.value):t.value;soon()}});
  // Money columns show "1,000,262.50" (money()) once the field isn't focused and switch to the plain
  // digits while being edited, so commas never fight with the caret or get typed into the stored number.
  ed.addEventListener('focusin',e=>{const t=e.target;if(t.dataset.money){const r=rowRef(t);if(!r)return;const v=r[colKey(t)];t.value=v===""||v==null?"":String(v)}});
  ed.addEventListener('focusout',e=>{const t=e.target;
    if(t.dataset.phone&&t.dataset.k){const v=tidyPhone(t.value);if(v!==t.value){t.value=v;S[CUR_DOC][t.dataset.k]=v;soon()}return}
    if(t.dataset.money){const r=rowRef(t);if(!r)return;const v=moneyParse(t.value);r[colKey(t)]=v;t.value=v===""?"":money(v)}});
  ed.addEventListener('change',e=>{const t=e.target;
    if(t.dataset.inc){S.board.inc=S.board.inc||{};S.board.inc[t.dataset.inc]=t.checked?1:0;soon();return}
    if(t.dataset.idpick!==undefined){const n=t.dataset.idpick,P=S.idcard.pick;if(t.checked){if(!P.some(x=>low(x)===low(n)))P.push(n)}else S.idcard.pick=P.filter(x=>low(x)!==low(n));redraw();return}
    if(t.dataset.pfpart!==undefined){const H=new Set(Array.isArray(S.profile.hide)?S.profile.hide:[]);if(t.checked)H.delete(t.dataset.pfpart);else H.add(t.dataset.pfpart);S.profile.hide=[...H];soon();return}
    if(CUR_DOC==="fin"&&t.dataset.fin==="sortBy"&&t.value){finSortRows(t.value);t.value="";return}
    if(['cover','board','letters','inv','pay','po','fin'].includes(CUR_DOC)&&(t.dataset.k||t.dataset.l)){clearTimeout(tm);tm=setTimeout(()=>{drawEditor();drawPreview()},250)}});
  ed.addEventListener('click',e=>{const b=e.target.closest('button');if(!b||b.disabled)return;const d=S[CUR_DOC];
    if(b.dataset.lang){d.lang=b.dataset.lang;redraw();return}
    if(b.dataset.mode){d.mode=b.dataset.mode;redraw();return}
    if(b.dataset.ltype){d.type=b.dataset.ltype;redraw();return}
    if(CUR_DOC==="fin"&&b.dataset.fin){finAction(b.dataset.fin,b);return}
    const a=b.dataset.act;if(!a)return;
    if(a==='idAll'){d.pick=((CFG.ctx&&CFG.ctx.employees)||[]).filter(x=>x.status!=='left').map(x=>x.nameEn);redraw();return}
    if(a==='idNone'){d.pick=[];redraw();return}
    if(a==='photoDel'){d.photo='';redraw();return}
    if(a==='pfAll'){d.hide=[];redraw();return}
    if(a==='pfBasic'){d.hide=PFPARTS.map(p=>p[0]).filter(k=>!PFBASIC.includes(k));redraw();return}
    if(a==='payClear'){d.staff.forEach(x=>{x.ot=0;x.ded=0;x.adv=0});redraw();return}
    if(a==='useInvTotal'){const sub=d.items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0);d.rAmount=Math.round((sub-n2(d.discount)-n2(d.paid))*100)/100;if(!d.rFor)d.rFor=(d.lang==='ar'?'الفاتورة رقم ':'Invoice ')+d.no;redraw();return}
    const L=d[b.dataset.l],i=+b.dataset.i;if(!L)return;
    if(a==='del'&&b.dataset.confirmRow&&window.Modal){
      const dar=UI_AR;
      window.Modal.confirm({tone:'danger',title:dar?'حذف هذا السطر؟':'Remove this line?',message:dar?'يحتوي هذا السطر على بيانات — سيتم حذفها نهائياً.':'This line has data in it — it will be removed for good.'})
        .then(ok=>{if(ok){L.splice(i,1);redraw()}});
      return;
    }
    if(a==='add')L.push(NEWROW[b.dataset.l]?NEWROW[b.dataset.l]():{});
    if(a==='del')L.splice(i,1);
    if(a==='up'&&i>0)[L[i-1],L[i]]=[L[i],L[i-1]];
    if(a==='down'&&i<L.length-1)[L[i],L[i+1]]=[L[i+1],L[i]];
    redraw();
    if(a==='add'){const ins=ed.querySelectorAll(`[data-l="${b.dataset.l}"][data-i="${L.length-1}"]`);if(ins[0])ins[0].focus()}});
}

/* ---------- toolbar: letterhead, record, save / issue ---------- */
const lhSel=$('#lhSel');if(lhSel){lhSel.value=String(LH);lhSel.addEventListener('change',()=>{LH=+lhSel.value;drawPreview()})}
const rec=$('#recPick');if(rec&&CFG.record&&CFG.record.base){rec.addEventListener('change',()=>{if(rec.value)location.href=CFG.record.base+encodeURIComponent(rec.value)})}
function post(url,fields){const f=document.createElement('form');f.method='post';f.action=url;f.hidden=true;
  Object.entries(Object.assign({_csrf:csrf},fields)).forEach(([k,v])=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=v==null?'':String(v);f.appendChild(i)});document.body.appendChild(f);f.submit()}
/* ---------- Financial Report: bills/lines AJAX (draft mode only) ----------
 * Every call below resolves (never rejects) with {ok, status, body} — network/JSON failures just come
 * back as ok:false, so callers never need a .catch for the fetch itself. */
function finFetch(url,fields){
  const fd=new FormData();fd.append('_csrf',csrf);
  Object.entries(fields||{}).forEach(([k,v])=>{if(Array.isArray(v))v.forEach(x=>fd.append(k+'[]',x));else fd.append(k,v==null?'':String(v))});
  return fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json().catch(()=>({})).then(j=>({ok:r.ok,status:r.status,body:j})))
    .catch(()=>({ok:false,status:0,body:{}}));
}
function finErr(res){if(window.Modal)window.Modal.toast((res.body&&res.body.error)||T.finNotDraft||'Error',true);else alert((res.body&&res.body.error)||T.finNotDraft||'Error')}
function finReplaceLines(lines){S.fin.rows=(lines||[]).map(finLineFromServer);redraw()}
function finOpenPicker(selectAllOnOpen,fromMonth){
  if(!window.BillReportPicker)return;
  const d=S.fin,kind=d.reportKind||'outstanding';
  const[pf,pt]=fromMonth?finMonthRange(d.periodMonth||today().slice(0,7)):[d.periodFrom||'',d.periodTo||''];
  const statusFor={outstanding:'unpaid'}[kind]||'';
  const typeFor={expenses:'expense',income:'income'}[kind]||'';
  const already={};d.rows.forEach(r=>{if(r.billId)already[r.billId]=true});
  window.BillReportPicker.open({
    title:selectAllOnOpen?(T.finMonthlyReport||'Monthly report'):(T.finAddBills||'Add bills'),
    apiUrl:CFG.finApi.bills,already,selectAllOnOpen,
    presetFilters:{from:pf,to:pt,type:selectAllOnOpen?'':typeFor,status:selectAllOnOpen?'':statusFor},
    onAdd:ids=>finFetch(CFG.finApi.addBills,{bill_ids:ids}).then(res=>{
      if(!res.ok){finErr(res);throw new Error('add failed')}
      finReplaceLines(res.body.lines);
      if(window.Modal)window.Modal.toast(trS(`${res.body.added} ${res.body.added===1?'bill':'bills'} added`+(res.body.already?`, ${res.body.already} already in this report`:'')));
    })
  });
}
function finAddManualLine(){
  finFetch(CFG.finApi.addManual,{en:'',ar:'',amount:0,type:'expense',status:'pending'}).then(res=>{
    if(!res.ok){finErr(res);return}
    finReplaceLines(res.body.lines);
  });
}
function finDoRefresh(){
  finFetch(CFG.finApi.refresh,{}).then(res=>{
    if(!res.ok){finErr(res);return}
    finReplaceLines(res.body.lines);
    const n=(res.body.changed||[]).length;
    const msg=n?`${T.finRefreshed||'Updated.'} ${(T.finRefreshChanged||':n line(s) updated.').replace(':n',n)}`:(T.finNoChanges||'Nothing changed.');
    if(window.Modal)window.Modal.toast(msg);
  });
}
function finRemoveLine(lineId){
  if(!lineId)return;
  const go=()=>finFetch(CFG.finApi.removeLine,{line_id:lineId}).then(res=>{if(!res.ok){finErr(res);return}finReplaceLines(res.body.lines)});
  const dar=UI_AR;
  if(window.Modal)window.Modal.confirm({tone:'danger',title:dar?'إزالة البند':'Remove line',message:T.finRemoveConfirm||(dar?'سيُحذف هذا البند من التقرير.':'This line will be removed from the report.')}).then(ok=>{if(ok)go()});
  else if(confirm(T.finRemoveConfirm||'Remove this line from the report?'))go();
}
function finPersistOrder(){finFetch(CFG.finApi.reorder,{ids:S.fin.rows.map(r=>r.lineId)}).then(res=>{if(!res.ok)finErr(res)})}
function finSortRows(key){
  const d=S.fin;
  const val={
    date:r=>r.date||'9999-99-99',
    supplier:r=>(d.lang==='ar'?(r.partyAr||r.en||r.ar||r.partyEn):(r.partyEn||r.en||r.ar||r.partyAr))?.toLowerCase()||'',
    amount:r=>-n2(r.amt),
    category:r=>((d.lang==='ar'?(r.catAr||r.catEn):(r.catEn||r.catAr))||'').toLowerCase(),
  }[key];
  if(!val)return;
  d.rows.sort((a,b)=>{const x=val(a),y=val(b);return x<y?-1:x>y?1:0});
  finPersistOrder();redraw();
}
function finAction(fa,b){
  const d=S.fin;
  if(fa==='periodMonth'){d.periodMode='month';if(!d.periodMonth)d.periodMonth=(d.periodFrom||today()).slice(0,7);redraw();return}
  if(fa==='periodCustom'){d.periodMode='custom';if(!d.periodFrom){const[pf,pt]=finMonthRange(d.periodMonth||today().slice(0,7));d.periodFrom=pf;d.periodTo=pt}redraw();return}
  if(fa==='addBills'){finOpenPicker(false,false);return}
  if(fa==='monthly'){finOpenPicker(true,true);return}
  if(fa==='addManual'){finAddManualLine();return}
  if(fa==='refresh'){finDoRefresh();return}
  if(fa==='up'){const i=+b.dataset.i;if(i>0){[d.rows[i-1],d.rows[i]]=[d.rows[i],d.rows[i-1]];finPersistOrder();redraw()}return}
  if(fa==='down'){const i=+b.dataset.i;if(i<d.rows.length-1){[d.rows[i],d.rows[i+1]]=[d.rows[i+1],d.rows[i]];finPersistOrder();redraw()}return}
  if(fa==='remove'){finRemoveLine(+b.dataset.line);return}
}
/* "Issue document" is a positive action (gets a reference number, saved to the archive) — it must never
 * look like a delete confirmation. Before opening the dialog: block on missing required fields (so e.g.
 * a horse profile can't be issued as "Unnamed horse"), and warn (but allow) on an incomplete pedigree. */
const saveBtn=$('#saveBtn');
let issuing=false;
const uiAr=CFG.lang==='ar';
const ISSUE_ICON='<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 9h8M8 13h5"/><path d="M8 17l2 2 4-4"/></svg>';
function pedigreeWarning(){
  if(CUR_DOC!=='profile')return null;
  // the first three generations only: further back, unknown ancestors are common and not worth a warning
  const PATHS=['s','d','ss','sd','ds','dd','sss','ssd','sds','sdd','dss','dsd','dds','ddd'];
  const filled=PATHS.filter(p=>((S[CUR_DOC]['p_'+p]||'')+'').trim()!=='').length;
  if(filled>=PATHS.length)return null;
  return (T.pedigreeWarn||'The first three generations of the pedigree are incomplete (:n / 14). Issue anyway?').replace(':n',filled);
}
// The fields that name what a document is about, when it is typed by hand instead of picked in "Fill from"
// (an invoice's customer, a PO's supplier, ...). ID cards are about the ticked employees, payslips their staff lines.
const SUBJECT={profile:['nameEn','nameAr'],diet:['horse'],vet:['horse'],cover:['mare','stallion'],board:['ownerEn','ownerAr'],embryo:['code','name'],
  sal:['nameEn','nameAr'],letters:['nameEn','nameAr'],offer:['nameEn','nameAr'],po:['supplier','supplierAr'],inv:['cName','cNameAr']};
function subjectTyped(d){
  if(CUR_DOC==='idcard')return Array.isArray(d.pick)&&d.pick.length>0;
  if(CUR_DOC==='pay')return Array.isArray(d.staff)&&d.staff.some(r=>((r.name||r.nameAr||'')+'').trim()!=='');
  return (SUBJECT[CUR_DOC]||['name','nameEn']).some(k=>((d[k]||'')+'').trim()!=='');
}
// "Customer › Name (English)" as drawn on the form (in Arabic when the interface is), or null when there is no such field
function subjectLabel(){
  if(CUR_DOC==='idcard'){const sec=document.querySelector('.idlist')&&document.querySelector('.idlist').closest('.sec'),h=sec&&sec.querySelector('h3');return h?h.textContent.trim():null}
  const k=(SUBJECT[CUR_DOC]||[])[0],lab=k&&document.querySelector('label[for="f-'+CUR_DOC+'-'+k+'"]');
  if(!lab)return null;
  const h=lab.closest('.sec')&&lab.closest('.sec').querySelector('h3');
  return (h?h.textContent.trim()+' › ':'')+lab.textContent.trim();
}
function missingFieldLabels(){
  const miss=[],d=S[CUR_DOC];
  // Types with a linked record ("Fill from") need either a picked record or what the document is about typed by hand.
  if(CFG.record&&CFG.record.source&&!CFG.record.id&&!subjectTyped(d))miss.push(subjectLabel()||T.sumRecord||(uiAr?'التعبئة من':'Fill from'));
  return miss;
}
function issueSummary(){
  const rows=[[T.sumType||(uiAr?'المستند':'Document'),CFG.docTypeLabel||CUR_DOC]];
  if(CFG.record&&CFG.record.label)rows.push([T.sumRecord||(uiAr?'السجل':'Record'),CFG.record.label]);
  const docAr=(S[CUR_DOC].lang||CFG.lang)==='ar';
  rows.push([T.sumLang||(uiAr?'اللغة':'Language'), docAr?(uiAr?'العربية':'Arabic'):(uiAr?'الإنجليزية':'English')]);
  return rows;
}
const draftBtn=$('#draftBtn');
/* The fin draft save/issue round-trip through the server (JSON), unlike every other type's one-shot
 * classic form POST+redirect — a draft stays on the same page, and issuing may come back with a
 * duplicate-report warning to re-confirm instead of a hard failure. */
function finSaveDraft(silent){
  return finFetch(CFG.saveUrl,{data:JSON.stringify(S.fin),lang:S.fin.lang||CFG.lang,letterhead:LH}).then(res=>{
    if(!res.ok){finErr(res);return false}
    if(!silent&&window.Modal)window.Modal.toast(T.draftSaved||'Draft saved.');
    return true;
  });
}
function finIssue(override){
  issuing=true;saveBtn.disabled=true;
  return finFetch(CFG.finApi.issue,{data:JSON.stringify(S.fin),lang:S.fin.lang||CFG.lang,letterhead:LH,override:override?1:0}).then(res=>{
    issuing=false;saveBtn.disabled=false;
    const body=res.body||{};
    if(res.ok&&body.ok){window.location.href=body.redirect;return}
    if(body.warning&&!override){
      const retry=ok=>{if(ok)finIssue(true)};
      if(window.Modal)window.Modal.confirm({tone:'danger',title:uiAr?'تحذير تكرار':'Duplicate warning',message:body.warning,okLabel:saveBtn.textContent.trim()}).then(retry);
      else retry(confirm(body.warning));
      return;
    }
    finErr(res);
  });
}
if(draftBtn)draftBtn.addEventListener('click',()=>{
  if(issuing)return;issuing=true;draftBtn.disabled=true;
  finSaveDraft(false).finally(()=>{issuing=false;draftBtn.disabled=false});
});
if(saveBtn)saveBtn.addEventListener('click',()=>{
  if(issuing)return; // guards a rapid double-click from opening two stacked confirm dialogs
  if(CFG.mode==='draft'){
    if(!saveBtn.dataset.confirm){finIssue(false);return}
    issuing=true;
    const opts={title:saveBtn.textContent.trim(),message:saveBtn.dataset.confirm,summary:issueSummary(),okLabel:saveBtn.textContent.trim(),tone:'primary',icon:ISSUE_ICON};
    const done=ok=>{issuing=false;if(ok)finIssue(false)};
    if(window.Modal)window.Modal.confirm(opts).then(done);
    else done(confirm(saveBtn.dataset.confirm));
    return;
  }
  const go=()=>{saveBtn.disabled=true;post(CFG.saveUrl,{data:JSON.stringify(S[CUR_DOC]),lang:S[CUR_DOC].lang||CFG.lang,letterhead:LH,record_type:(CFG.record&&CFG.record.id)?CFG.record.kind:'',record_id:(CFG.record&&CFG.record.id)||''})};
  if(CFG.mode==='new'&&saveBtn.dataset.confirm){
    const miss=missingFieldLabels();
    if(miss.length){
      const fill=T.sumRecord||(uiAr?'التعبئة من':'Fill from');
      const msg=T.missingSubject&&miss[0]!==fill?T.missingSubject.replace(':field',miss[0]).replace(':fill',fill)
        :(T.missingFields||(uiAr?'قبل الإصدار، يرجى تعبئة: :fields.':'Before issuing, fill in: :fields.')).replace(':fields',miss.join(uiAr?'، ':', '));
      if(window.Modal)window.Modal.toast(msg,'error');else alert(msg);
      return;
    }
    issuing=true;
    const opts={title:saveBtn.textContent.trim(),message:saveBtn.dataset.confirm,summary:issueSummary(),okLabel:saveBtn.textContent.trim(),tone:'primary',icon:ISSUE_ICON};
    const pedWarn=pedigreeWarning(); if(pedWarn)opts.warning=pedWarn;
    const done=ok=>{issuing=false;if(ok)go()};
    if(window.Modal)window.Modal.confirm(opts).then(done);
    else done(confirm(saveBtn.dataset.confirm));
  }else go();
});

/* ---------- print, PDF and share (each one is recorded in the Activity Log) ---------- */
function log(action,via){const url=CFG.id?CFG.logUrl:CFG.logUrl;const fd=new FormData();fd.append('_csrf',csrf);fd.append('action',action);if(via)fd.append('via',via);if(!CFG.id)fd.append('type',CUR_DOC);
  try{fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})}catch(e){}}
function toast(t,bad){let el=$('#studioToast');if(!el){el=document.createElement('div');el.id='studioToast';el.className='studio-toast';document.body.appendChild(el)}el.textContent=t;el.classList.remove('has-act');el.classList.toggle('bad',!!bad);el.classList.add('on');clearTimeout(toast.t);toast.t=setTimeout(()=>el.classList.remove('on'),4200)}
// Fonts embedded in the PDF come from this site's own stylesheets only (the documents use the local IBM Plex / Amiri /
// EB Garamond files). Collected in a hidden same-origin frame so html-to-image never touches the cross-origin Google
// Fonts sheet of the portal chrome, whose rules the browser won't expose and whose fetch the CSP refuses.
async function localFontCSS(){const f=document.createElement('iframe');f.style.cssText='position:fixed;left:-9999px;top:0;width:10px;height:10px;border:0';document.body.appendChild(f);
  try{const d=f.contentDocument;d.open();d.write('<!doctype html><html><head></head><body></body></html>');d.close();
    const own=[...document.querySelectorAll('link[rel="stylesheet"]')].filter(l=>new URL(l.href,location.href).origin===location.origin);
    await Promise.all(own.map(l=>new Promise(r=>{const n=d.createElement('link');n.rel='stylesheet';n.href=l.href;n.onload=n.onerror=r;d.head.appendChild(n)})));
    const el=d.createElement('div');d.body.appendChild(el);return await htmlToImage.getFontEmbedCSS(el)}finally{f.remove()}}
async function makePdf(btn){
  if(!window.htmlToImage||!window.jspdf)throw new Error('libs');
  const old=btn?btn.textContent:'';if(btn){btn.disabled=true;btn.textContent=T.preparing||'…'}
  host.style.zoom=1;
  try{if(document.fonts)await document.fonts.ready;
    const pages=[...host.querySelectorAll('.page')];const fontCSS=await localFontCSS();
    const isCard=pages[0].classList.contains('cardpage'),PW=isCard?85.6:210,PH=isCard?54:297,fmt=isCard?[PW,PH]:'a4',ori=isCard?'landscape':'portrait';
    const pdf=new window.jspdf.jsPDF({unit:'mm',format:fmt,orientation:ori,compress:true});
    for(let i=0;i<pages.length;i++){if(btn)btn.textContent=`${i+1} / ${pages.length}…`;
      const url=await htmlToImage.toJpeg(pages[i],{quality:.94,pixelRatio:isCard?6:2.5,backgroundColor:'#ffffff',fontEmbedCSS:fontCSS,style:{boxShadow:'none',margin:'0',borderRadius:'0'}});
      if(i)pdf.addPage(fmt,ori);pdf.addImage(url,'JPEG',0,0,PW,PH,undefined,'FAST')}
    pdf.setProperties({title:CFG.fileName||CUR_DOC,subject:'SK Arabian Studio',creator:'SK Arabians Management System'});
    return pdf.output('blob');
  }finally{fit();if(btn){btn.disabled=false;btn.textContent=old}}}
const fileName=()=>(CFG.fileName||('SK-Arabian-'+CUR_DOC))+'.pdf';
function download(blob){const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=fileName();document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},1500)}
const printBtn=$('#printBtn');if(printBtn)printBtn.addEventListener('click',()=>{log('print');host.style.zoom=1;window.print();fit()});
/* "PDF ready": the file is offered with a Download button once it is built (a download started by itself after
   the wait is blocked by some browsers, and the user sees nothing happen). */
function pdfReady(blob){let el=$('#studioToast');if(!el){el=document.createElement('div');el.id='studioToast';el.className='studio-toast';document.body.appendChild(el)}
  clearTimeout(toast.t);if(pdfReady.url)URL.revokeObjectURL(pdfReady.url);pdfReady.url=URL.createObjectURL(blob);
  el.innerHTML=`<span class="st-msg">${esc(T.pdfReady||'PDF ready')}</span><a class="st-btn" href="${pdfReady.url}" download="${esc(fileName())}">${esc(T.pdfDownload||'Download')}</a><button type="button" class="st-x" aria-label="${esc(T.close||'Close')}">×</button>`;
  el.setAttribute('role','status');el.classList.remove('bad');el.classList.add('on','has-act');
  el.querySelector('.st-btn').addEventListener('click',()=>{log('download');setTimeout(()=>el.classList.remove('on'),400)});
  el.querySelector('.st-x').addEventListener('click',()=>el.classList.remove('on'));
  el.querySelector('.st-btn').focus({preventScroll:true});
  toast.t=setTimeout(()=>el.classList.remove('on'),20000)}
const pdfBtn=$('#pdfBtn');if(pdfBtn)pdfBtn.addEventListener('click',async()=>{try{pdfReady(await makePdf(pdfBtn))}catch(e){console.error(e);toast(T.pdfFailed||'PDF failed',1)}});
const shareBtn=$('#shareBtn');if(shareBtn)shareBtn.addEventListener('click',async()=>{
  try{const blob=await makePdf(shareBtn);const file=new File([blob],fileName(),{type:'application/pdf'});
    if(navigator.canShare&&navigator.canShare({files:[file]})){await navigator.share({files:[file],title:CFG.fileName});log('share','device');return}
    const text=encodeURIComponent((CFG.fileName||'')+'\n'+(T.verifyAt||'')+' '+(CFG.verifyUrl||''));window.open('https://wa.me/?text='+text,'_blank','noopener');log('share','whatsapp');
  }catch(e){if(e&&e.name==='AbortError')return;console.error(e);toast(T.pdfFailed||'PDF failed',1)}});
const mailForm=$('#mailForm');if(mailForm)mailForm.addEventListener('submit',async e=>{e.preventDefault();const btn=mailForm.querySelector('button[type=submit]');
  try{const blob=await makePdf(btn);const fd=new FormData(mailForm);fd.append('pdf',blob,fileName());
    const r=await fetch(mailForm.action,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
    const j=await r.json().catch(()=>({}));toast(j.message||j.error||(r.ok?'OK':'Error'),!r.ok||!j.ok);if(r.ok&&j.ok)mailForm.reset();
  }catch(err){console.error(err);toast(T.pdfFailed||'PDF failed',1)}});

/* ---------- start ---------- */
window.addEventListener('resize',()=>{clearTimeout(fit.t);fit.t=setTimeout(fit,120)});
if(!VIEW)drawEditor();
zoomBar();viewTabs();
drawPreview();
if(document.fonts)document.fonts.ready.then(drawPreview);
if(CFG.mode==='print'){window.addEventListener('load',()=>setTimeout(()=>{log('print');window.print()},500))}
window.SKEditor={get data(){return S[CUR_DOC]},redraw,makePdf};
})();
