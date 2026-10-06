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
const CUR_DOC=CFG.type,S={},host=$('#pages'),canvas=$('#canvas'),ed=$('#editor');
const csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||CFG.csrf||'';
let LH=+CFG.letterhead||2;

/* ---------- initial data ---------- */
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

/* ---------- form helpers (original Studio) ---------- */
const fld=(k,label,o={})=>{const v=S[CUR_DOC][k]??"";const id=`f-${CUR_DOC}-${k}`;const dir=o.ar?' dir="rtl"':"";const cls=`f${o.wide?" wide":""}`;
  if(o.area)return `<div class="${cls}"><label for="${id}">${label}</label><textarea id="${id}" class="in" data-k="${k}" rows="${o.area}"${dir}>${esc(v)}</textarea></div>`;
  if(o.opts)return `<div class="${cls}"><label for="${id}">${label}</label><select id="${id}" data-k="${k}">${o.opts.map(([a,b])=>`<option value="${a}"${String(v)===String(a)?" selected":""}>${b}</option>`).join("")}</select></div>`;
  return `<div class="${cls}"><label for="${id}">${label}</label><input id="${id}" class="in" data-k="${k}" type="${o.type||"text"}" value="${esc(v)}"${o.type==="number"?' step="any"':""}${dir}${o.ph?` placeholder="${esc(o.ph)}"`:""}></div>`};
const sec=(t,inner,one)=>`<div class="sec"><h3>${t}</h3><div class="grid${one?" one":""}">${inner}</div></div>`;
const langSeg=()=>{const v=S[CUR_DOC].lang;return `<div class="seg" role="group" aria-label="Document language"><button type="button" data-lang="en" aria-pressed="${v==="en"}">English</button><button type="button" data-lang="ar" aria-pressed="${v==="ar"}">العربية</button></div>`};
function listEd(key,cols,addLabel,minw){
  const rows=S[CUR_DOC][key]||(S[CUR_DOC][key]=[]);
  return `<div class="lt-wrap"><table class="lt cardable"${minw?` style="min-width:${minw}"`:""}><thead><tr>${cols.map(c=>`<th style="${c.w?`width:${c.w}`:""}">${c.label}</th>`).join("")}<th></th></tr></thead><tbody>${rows.map((r,i)=>`<tr${cols[1]&&cols[1].ar?' class="has-ar"':""}>${cols.map(c=>`<td data-label="${esc(c.label)}">${c.opts?`<select data-l="${key}" data-i="${i}" data-c="${c.c}" aria-label="${c.label}">${c.opts.map(([a,b])=>`<option value="${a}"${r[c.c]===a?" selected":""}>${b}</option>`).join("")}</select>`:`<input data-l="${key}" data-i="${i}" data-c="${c.c}" aria-label="${c.label} ${i+1}" value="${esc(r[c.c])}"${c.num?' type="number" step="any"':""}${c.date?' type="date"':""}${c.ar?' dir="rtl"':""}>`}</td>`).join("")}<td class="ctl"><button class="ico" type="button" data-act="up" data-l="${key}" data-i="${i}" title="Move up">↑</button><button class="ico del" type="button" data-act="del" data-l="${key}" data-i="${i}" title="Remove">✕</button></td></tr>`).join("")}</tbody></table></div>
  <button class="btn small addrow" type="button" data-act="add" data-l="${key}">+ ${addLabel}</button>`;
}
function cardEd(key,fields,title,addLabel){
  const rows=S[CUR_DOC][key]||(S[CUR_DOC][key]=[]);
  return rows.map((r,i)=>`<div class="card"><div class="card-h"><span>${title(r,i)}</span><span><button class="ico" type="button" data-act="up" data-l="${key}" data-i="${i}" title="Move up">↑</button><button class="ico del" type="button" data-act="del" data-l="${key}" data-i="${i}" title="Remove">✕</button></span></div><div class="grid one">${fields.map(f=>`<div class="f"><label>${f.label}</label>${f.area?`<textarea class="in" rows="${f.area}" data-l="${key}" data-i="${i}" data-c="${f.c}"${f.ar?' dir="rtl"':""}>${esc(r[f.c])}</textarea>`:`<input class="in" data-l="${key}" data-i="${i}" data-c="${f.c}" value="${esc(r[f.c])}"${f.ar?' dir="rtl"':""}>`}</div>`).join("")}</div></div>`).join("")+`<button class="btn small" type="button" data-act="add" data-l="${key}">+ ${addLabel}</button>`;
}
const CURS=Object.keys(CUR).map(k=>[k,`${k} — ${CUR[k].en}`]);
const SEXO=Object.entries(SEX).map(([k,v])=>[k,`${v[0]} / ${v[1]}`]);
const pedFld=p=>fld("p_"+p,pedLabel(p,false));
function feedEd(){const d=S.diet,sl=d.slots||[];return listEd("items",[{c:"name",label:"Feed",w:"170px"},...sl.map((s,i)=>({c:"s"+i,label:esc(s.en),w:"96px"}))],"Add feed","760px")+`<p class="hint" style="margin-top:8px">Filled from the horse's diet plan in the Horses module. Leave the grid empty to print a blank sheet for the grooms.</p>`}

const ED={};
ED.fin=()=>{const d=S.fin,t=d.rows.reduce((s,r)=>s+n2(r.amt),0);return `<div class="ed-h"><h2>Financial Report</h2>${langSeg()}</div><p class="hint">Payables statement with totals, top payees and salary split worked out for you. Mark salary lines as “Salary”.</p>`+
 sec("Report",fld("date","Report date",{type:"date"})+fld("currency","Currency",{opts:CURS})+fld("titleEn","Title (English)",{wide:1})+fld("titleAr","Title (Arabic)",{wide:1,ar:1})+fld("subEn","Subtitle (English)",{wide:1})+fld("subAr","Subtitle (Arabic)",{wide:1,ar:1}))+
 `<div class="sec"><h3>Line items</h3>${listEd("rows",[{c:"en",label:"Name (English)",w:"170px"},{c:"ar",label:"Name (Arabic)",ar:1,w:"150px"},{c:"qty",label:"Qty",num:1,w:"56px"},{c:"amt",label:"Amount",num:1,w:"120px"},{c:"type",label:"Type",opts:[["s","Supplier"],["w","Salary"]],w:"96px"},{c:"status",label:"Status",opts:[["pending","Pending"],["paid","Paid"],["partial","Partial"]],w:"96px"}],"Add line")}
 <div class="sum">Lines <b>${d.rows.length}</b> Total <b>${money(t)}</b></div></div>`+
 sec("Signatures",fld("prepared","Prepared by (name)")+fld("approved","Approved by (name)"))};
ED.diet=()=>`<div class="ed-h"><h2>Diet Log</h2>${langSeg()}</div><p class="hint">Fill in the horse details. Leave the feed grid empty to print a blank sheet for the grooms, or enter amounts to print a filled plan.</p>`+
 sec("Horse",fld("horse","Horse name")+fld("dob","Date of birth")+fld("sire","Sire")+fld("dam","Dam")+fld("preg","Pregnancy status (English)")+fld("pregAr","Pregnancy status (Arabic)",{ar:1})+fld("box","Stable / box no.")+fld("working","Working / in training",{opts:[["","Not set"],["yes","Yes"],["no","No"]]})+fld("work","Work details (English)")+fld("workAr","Work details (Arabic)",{ar:1})+fld("date","Log date (optional)",{type:"date"}))+
 `<div class="sec"><h3>Feed plan</h3>${feedEd()}</div>`+
 sec("Notes & sign-off",fld("notes","Notes / instructions (English)",{area:3,wide:1})+fld("notesAr","Notes / instructions (Arabic)",{area:3,wide:1,ar:1})+fld("recorded","Recorded by")+fld("checked","Checked by"));
ED.po=()=>{const d=S.po,sub=d.items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0);return `<div class="ed-h"><h2>Purchase Order</h2>${langSeg()}</div><p class="hint">Line totals, the grand total and the amount in words are calculated automatically. The sample items are placeholders.</p>`+
 sec("Order",fld("no","PO number")+fld("date","PO date",{type:"date"})+fld("delivery","Delivery date",{type:"date"})+fld("currency","Currency",{opts:CURS})+fld("ref","Quotation / reference (optional)",{wide:1}))+
 sec("Supplier",fld("supplier","Supplier name (English)")+fld("supplierAr","Supplier name (Arabic)",{ar:1})+fld("contact","Contact person")+fld("phone","Phone")+fld("email","Email",{wide:1}))+
 sec("Delivery & payment",fld("shipTo","Deliver to (English)")+fld("shipToAr","Deliver to (Arabic)",{ar:1})+fld("payment","Payment terms (English)")+fld("paymentAr","Payment terms (Arabic)",{ar:1}))+
 `<div class="sec"><h3>Items</h3>${listEd("items",[{c:"d",label:"Description",w:"220px"},{c:"dAr",label:"Description (Arabic)",ar:1,w:"200px"},{c:"q",label:"Qty",num:1,w:"64px"},{c:"u",label:"Unit",w:"76px"},{c:"p",label:"Unit price",num:1,w:"96px"}],"Add item")}
 <div class="sum">Subtotal <b>${money(sub)}</b> Total <b>${money(sub-n2(d.discount))}</b></div></div>`+
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
 (isR?sec("Payment",fld("rFor","Being payment for (English)",{wide:1,ph:"e.g. Invoice SK/INV/2026/001 – boarding October"})+fld("rForAr","Being payment for (Arabic)",{wide:1,ar:1})+fld("rMethod","Payment method",{opts:[["cash","Cash"],["cheque","Cheque"],["transfer","Bank transfer"],["card","Card"]]})+fld("rRef","Cheque / reference no.")+fld("rBank","Bank")+fld("receivedBy","Received by"))+`<div class="sec"><button class="btn small" type="button" data-act="useInvTotal">Use the invoice total (${money(sub-n2(d.discount)-n2(d.paid))})</button></div>`
     :`<div class="sec"><h3>Items</h3>${listEd("items",[{c:"d",label:"Description",w:"240px"},{c:"dAr",label:"Description (Arabic)",ar:1,w:"200px"},{c:"q",label:"Qty",num:1,w:"64px"},{c:"p",label:"Unit price",num:1,w:"110px"}],"Add item")}<div class="sum">Subtotal <b>${money(sub)}</b> Balance due <b>${money(sub-n2(d.discount)-n2(d.paid))}</b></div></div>`+
      sec("Totals & payment",fld("discount","Discount",{type:"number"})+fld("paid","Already paid",{type:"number"})+fld("payInfo","Payment details (English)",{area:2,wide:1})+fld("payInfoAr","Payment details (Arabic)",{area:2,wide:1,ar:1})))};
ED.pay=()=>{const d=S.pay,tot=d.staff.reduce((s,r)=>s+payNet(r).net,0);
 return `<div class="ed-h"><h2>Payslips</h2>${langSeg()}</div><p class="hint">Payslips come from the approved payroll. Each payslip is filled from its payroll line; open a payroll run to print all payslips of the month at once, two per page.</p>`+
 sec("Month",fld("month","Month",{type:"month"})+fld("payDate","Pay date",{type:"date"})+fld("method","Payment method")+fld("summary","Print payroll summary page",{opts:[["yes","Yes"],["no","No"]]})+fld("prepared","Prepared by",{wide:1}))+
 `<div class="sec"><h3>Staff</h3>${listEd("staff",[{c:"name",label:"Name (English)",w:"170px"},{c:"nameAr",label:"Name (Arabic)",ar:1,w:"150px"},{c:"pos",label:"Position",w:"140px"},{c:"posAr",label:"Position (Arabic)",ar:1,w:"130px"},{c:"basic",label:"Basic",num:1,w:"90px"},{c:"allow",label:"Allowances",num:1,w:"90px"},{c:"ot",label:"Overtime / bonus",num:1,w:"90px"},{c:"ded",label:"Deductions",num:1,w:"90px"},{c:"adv",label:"Advance",num:1,w:"90px"}],"Add employee","1250px")}
 <div class="quick" style="margin-top:8px"><button class="btn small" type="button" data-act="payClear">Clear overtime, deductions & advances</button></div>
 <div class="sum">Staff <b>${d.staff.length}</b> Total net pay <b>${money(tot)}</b></div></div>`};
ED.vet=()=>{const d=S.vet,up=vetUpcoming(d),ST={over:"Overdue",soon:"Due soon",ok:"On schedule"};
 return `<div class="ed-h"><h2>Vet Record</h2>${langSeg()}</div><p class="hint">Log vaccinations, deworming, farrier and vet visits for each horse. Add the next due date and the record shows what is overdue or coming up.</p>`+
 sec("Horse",fld("horse","Horse name",{wide:1})+fld("sex","Sex",{opts:SEXO})+fld("dob","Date of birth")+fld("sire","Sire")+fld("dam","Dam")+fld("chip","Microchip no.")+fld("box","Stable / box no."))+
 (up.length?`<div class="sec"><h3>Coming up</h3><div class="upc">${up.map(e=>{const st=vetStatus(e.next);return `<div><span><b>${VT[e.type][0]}</b> · ${fmtDate(e.next,"en")}</span>${st?`<span class="st ${st}">${ST[st]}</span>`:""}</div>`}).join("")}</div></div>`:"")+
 `<div class="sec"><h3>Treatments</h3>${listEd("entries",[{c:"date",label:"Date",date:1,w:"150px"},{c:"type",label:"Type",opts:Object.entries(VT).map(([k,v])=>[k,v[0]]),w:"130px"},{c:"what",label:"Details",w:"200px"},{c:"whatAr",label:"Details (Arabic)",ar:1,w:"180px"},{c:"by",label:"By",w:"110px"},{c:"next",label:"Next due",date:1,w:"150px"}],"Add treatment","900px")}</div>`+
 sec("Notes",fld("notes","Notes (English)",{area:3,wide:1})+fld("notesAr","Notes (Arabic)",{area:3,wide:1,ar:1}))};
ED.cover=()=>{const d=S.cover,fd=foalDate(d);return `<div class="ed-h"><h2>Covering Certificate</h2>${langSeg()}</div><p class="hint">Record which stallion covered a mare and when. The expected foaling date is worked out from the last covering date.</p>`+
 sec("Certificate",fld("no","Certificate no.")+fld("date","Date",{type:"date"})+fld("season","Season"))+
 sec("Mare",fld("mare","Mare name",{wide:1})+fld("mareDob","Date of birth")+fld("mareReg","Passport / reg. no.")+fld("mareSire","Sire")+fld("mareDam","Dam")+fld("mareChip","Microchip no.",{wide:1}))+
 sec("Mare owner",fld("ownerEn","Owner (English)")+fld("ownerAr","Owner (Arabic)",{ar:1})+fld("ownerPhone","Phone",{wide:1}))+
 sec("Stallion",fld("stallion","Stallion name",{wide:1})+fld("stallionReg","Passport / reg. no.",{wide:1})+fld("stallionSire","Sire")+fld("stallionDam","Dam")+fld("stOwnerEn","Owner (English)")+fld("stOwnerAr","Owner (Arabic)",{ar:1}))+
 `<div class="sec"><h3>Covering</h3><div class="grid">${fld("method","Method",{opts:Object.entries(METH).map(([k,v])=>[k,v[0]])})+fld("gest","Gestation days",{type:"number"})}</div><div style="margin-top:12px">${listEd("covers",[{c:"date",label:"Covering date",date:1,w:"200px"}],"Add covering date","0")}</div>${fd?`<div class="calc"><div class="t"><span>Expected foaling</span><span>${fmtDate(fd,"en")}</span></div></div>`:""}</div>`+
 sec("Pregnancy check",fld("checkResult","Result",{opts:Object.entries(PREG).map(([k,v])=>[k,v[0]])})+fld("checkDate","Check date",{type:"date"})+fld("vet","Veterinarian",{wide:1})+fld("notes","Notes (English)",{area:2,wide:1})+fld("notesAr","Notes (Arabic)",{area:2,wide:1,ar:1}))};
ED.board=()=>{const d=S.board,n=(d.horses||[]).filter(h=>(h.name||"").trim()).length||1;return `<div class="ed-h"><h2>Boarding Agreement</h2>${langSeg()}</div><p class="hint">For owners who keep their horses at your stud. The end date and notice deadline are worked out for you and show up in Reminders.</p>`+
 sec("Agreement",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+fld("start","Start date",{type:"date"})+fld("term","Term (months)",{type:"number"}))+
 sec("Owner",fld("ownerEn","Name (English)")+fld("ownerAr","Name (Arabic)",{ar:1})+fld("ownerId","ID / C.R. no.")+fld("ownerPhone","Phone"))+
 `<div class="sec"><h3>Horses</h3>${listEd("horses",[{c:"name",label:"Horse name",w:"220px"},{c:"sex",label:"Sex",opts:SEXO,w:"170px"},{c:"box",label:"Box no.",w:"90px"}],"Add horse")}</div>`+
 sec("Fees",fld("fee","Monthly fee per horse",{type:"number"})+fld("currency","Currency",{opts:CURS})+fld("dueDay","Payment due (day of month)",{type:"number"})+fld("deposit","Deposit (0 = none)",{type:"number"})+fld("notice","Notice period (days)",{type:"number",wide:1}))+
 `<div class="calc"><div><span>End date</span><span>${fmtDate(boardEnd(d),"en")||"—"}</span></div><div class="t"><span>Total per month (${n} horse${n>1?"s":""})</span><span>${money(n2(d.fee)*n)} ${esc(d.currency)}</span></div></div>`+
 `<div class="sec"><h3>Included in the fee</h3><div class="chks">${Object.entries(INC).map(([k,v])=>`<label><input type="checkbox" data-inc="${k}"${d.inc&&d.inc[k]?" checked":""}>${v[0]}</label>`).join("")}</div><div class="grid one" style="margin-top:12px">${fld("extrasEn","Charged separately (English)",{area:2})+fld("extrasAr","Charged separately (Arabic)",{area:2,ar:1})}</div></div>`+
 `<div class="sec"><h3>Terms</h3><p class="hint">You can use {start}, {end}, {term}, {fee}, {deposit}, {notice} and {dueDay}. The deposit clause is left out when the deposit is 0.</p>${cardEd("clauses",[{c:"tEn",label:"Title (English)"},{c:"bEn",label:"Text (English)",area:3},{c:"tAr",label:"Title (Arabic)",ar:1},{c:"bAr",label:"Text (Arabic)",area:3,ar:1}],(r,i)=>`Term ${i+1} · ${esc(r.tEn||"")}`,"Add term")}</div>`};
ED.letters=()=>{const d=S.letters,t=d.type;const seg=`<div class="sec"><h3>Letter type</h3><div class="seg" role="group" aria-label="Letter type" style="flex-wrap:wrap">${Object.entries(LTYPE).map(([k,v])=>`<button type="button" data-ltype="${k}" aria-pressed="${t===k}">${v[0]}</button>`).join("")}</div></div>`;
 const bil=t==="exp"||t==="noc";
 let h=`<div class="ed-h"><h2>Staff Letters</h2>${bil?"":langSeg()}</div><p class="hint">${bil?"Prints English and Arabic side by side.":"Prints in English or Arabic."} Choose the employee above and their details are filled in from the Employees module.</p>`+seg+
  sec("Letter",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+(bil?fld("signName","Authorized signatory",{wide:1}):""))+
  sec("Employee",fld("gender","Employee",{opts:[["m","Male (Mr. / السيد)"],["f","Female (Ms. / السيدة)"]]})+fld("qid","Qatar ID no.")+fld("nameEn","Name (English)")+fld("nameAr","Name (Arabic)",{ar:1})+fld("natEn","Nationality (English)")+fld("natAr","Nationality (Arabic)",{ar:1})+fld("posEn","Position (English)")+fld("posAr","Position (Arabic)",{ar:1})+fld("joinDate","Joining date",{type:"date"})+fld("qidExp","QID valid until",{ph:"dd/mm/yyyy"}));
 if(t==="exp")h+=sec("Service",fld("endDate","Last working day (leave empty if still employed)",{type:"date",wide:1})+fld("toEn","Addressed to (English, optional)")+fld("toAr","Addressed to (Arabic, optional)",{ar:1}));
 if(t==="noc")h+=sec("Purpose",fld("toEn","Addressed to (English)")+fld("toAr","Addressed to (Arabic)",{ar:1})+fld("purposeEn","No objection to him/her… (English)",{wide:1})+fld("purposeAr","ولا مانع لدى الشركة من… (Arabic)",{wide:1,ar:1}));
 if(t==="leave"){const days=d.from&&d.to&&d.to>=d.from?dayDiff(d.from,d.to)+1:0;h+=sec("Leave",fld("leaveType","Type of leave",{opts:Object.entries(LEAVE).map(([k,v])=>[k,v[0]])})+`<div></div>`+fld("from","From",{type:"date"})+fld("to","To",{type:"date"})+fld("reason","Reason (English)")+fld("reasonAr","Reason (Arabic)",{ar:1})+fld("replacement","Replacement during leave")+fld("contact","Contact during leave"))+(days?`<div class="calc"><div><span>Number of days</span><span>${days}</span></div><div class="t"><span>Back to work</span><span>${fmtDate(addDays(d.to,1),"en")}</span></div></div>`:"")}
 if(t==="eos"){const c=eosCalc(d);h+=sec("Settlement",fld("endDate","Last working day",{type:"date"})+fld("eosReason","Reason",{opts:Object.entries(EOSR).map(([k,v])=>[k,v[0]])})+fld("basic","Basic monthly salary (QAR)",{type:"number"})+fld("dpy","Gratuity days per year",{type:"number"})+fld("leaveDays","Leave balance (days)",{type:"number"})+fld("unpaid","Unpaid salary",{type:"number"})+fld("other","Other dues",{type:"number"})+fld("deductions","Deductions & advances",{type:"number"})+fld("signName","Signed for the company by",{wide:1}))+
   `<div class="calc"><div><span>Service</span><span>${lenText(c.len,false)}</span></div><div><span>Gratuity</span><span>${money(c.grat)}</span></div><div><span>Leave balance</span><span>${money(c.leave)}</span></div><div class="t"><span>Net payable</span><span>${money(c.total)} QAR</span></div></div><p class="hint" style="margin-top:8px">Uses ${+d.dpy||21} days of basic salary per year of service (the Qatar Labour Law minimum is three weeks), nothing for under one year. Check the employee's contract for better terms.</p>`}
 return h};
ED.profile=()=>{const d=S.profile;return `<div class="ed-h"><h2>Horse Profile</h2>${langSeg()}</div><p class="hint">One paper per horse: photo, details, a 3-generation pedigree, show results and progeny. Choose the horse above and its details are filled in from the Horses module.</p>`+
 sec("Horse",fld("nameEn","Name (English)")+fld("nameAr","Name (Arabic)",{ar:1})+fld("sex","Sex",{opts:SEXO})+fld("dob","Date of birth",{ph:"e.g. Jan 29, 2021"})+fld("colourEn","Colour (English)")+fld("colourAr","Colour (Arabic)",{ar:1})+fld("breed","Breed (English)")+fld("breedAr","Breed (Arabic)",{ar:1})+fld("breeder","Breeder")+fld("owner","Owner")+fld("origin","Country of birth")+fld("height","Height",{ph:"e.g. 152 cm"})+fld("chip","Microchip no.")+fld("passport","Passport / reg. no.")+fld("status","Status",{opts:HSTAT})+fld("box","Stable / box no."))+
 `<div class="sec"><h3>Photo</h3><div class="ph-row">${d.photo?`<img class="ph-thumb" src="${d.photo}" alt="Horse photo">`:`<div class="ph-thumb ph-empty">No photo</div>`}<div class="quick" style="margin:0;flex-direction:column;align-items:flex-start">${d.photo?`<button type="button" class="btn small" data-act="photoDel">Remove photo</button>`:""}<span class="hint" style="margin:0">The main photo from the horse's profile is used. Change it on the horse page.</span></div></div></div>`+
 sec("Pedigree – sire side",pedFld("s")+`<div></div>`+pedFld("ss")+pedFld("sd")+["sss","ssd","sds","sdd"].map(pedFld).join(""))+
 sec("Pedigree – dam side",pedFld("d")+`<div></div>`+pedFld("ds")+pedFld("dd")+["dss","dsd","dds","ddd"].map(pedFld).join(""))+
 `<div class="sec"><h3>Show results & achievements</h3>${listEd("shows",[{c:"date",label:"Date",date:1,w:"150px"},{c:"show",label:"Show",w:"200px"},{c:"cls",label:"Class",w:"150px"},{c:"result",label:"Result",w:"130px"}],"Add result")}</div>`+
 `<div class="sec"><h3>Breeding program</h3><p class="hint">Planned and past matings. Once a mating is marked covered or in foal, the expected foaling date is worked out, and planned dates appear in Reminders.</p>${listEd("plan",[{c:"season",label:"Season",w:"80px"},{c:"partner",label:d.sex==="stallion"||d.sex==="colt"||d.sex==="gelding"?"Mare":"Stallion",w:"180px"},{c:"method",label:"Method",opts:[["","Not stated"],...Object.entries(METH).map(([k,v])=>[k,v[0]])],w:"160px"},{c:"date",label:"Date",date:1,w:"150px"},{c:"status",label:"Status",opts:Object.entries(PLANST).map(([k,v])=>[k,v[0]]),w:"140px"},{c:"notes",label:"Notes",w:"180px"}],"Add mating")}</div>`+
 `<div class="sec"><h3>Embryos</h3><p class="hint">Embryos flushed or produced (ET / ICSI): where they are stored, which recipient mare carries them, and what happened.</p>${listEd("embryos",[{c:"date",label:"Date",date:1,w:"150px"},{c:"partner",label:d.sex==="stallion"||d.sex==="colt"||d.sex==="gelding"?"Mare":"Sire",w:"170px"},{c:"method",label:"Method",opts:[["et","Embryo transfer"],["icsi","ICSI"]],w:"140px"},{c:"stage",label:"Stage",w:"120px"},{c:"grade",label:"Grade",w:"70px"},{c:"status",label:"Status",opts:Object.entries(EMBST).map(([k,v])=>[k,v[0]]),w:"140px"},{c:"recipient",label:"Recipient mare",w:"150px"},{c:"storage",label:"Storage / tank",w:"130px"}],"Add embryo")}</div>`+
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
 sec("Find",fld("filter","Document type",{opts:[["all","All documents"],...SK.types.filter(x=>x[0]!=="reg"&&x[0]!=="rem")]})+fld("q","Search reference or name"));
ED.embryo=()=>`<div class="ed-h"><h2>Embryo Transfer Record</h2>${langSeg()}</div><p class="hint">Choose the embryo above: donor mare, sire, recipient, dates and pregnancy checks are filled in from the Embryos module.</p>`+
 sec("Record",fld("no","Record no.")+fld("date","Date",{type:"date"})+fld("code","Embryo no.")+fld("name","Name (optional)")+fld("flushDate","Flush date",{type:"date"})+fld("status","Status",{opts:Object.entries(EST).map(([k,v])=>[k,v[0]])})+fld("grade","Grade")+fld("stage","Stage")+fld("storage","Storage location",{wide:1}))+
 sec("Owner",fld("ownerEn","Owner (English)")+fld("ownerAr","Owner (Arabic)",{ar:1}))+
 sec("Donor mare & sire",fld("donor","Donor mare")+fld("donorReg","Donor passport / reg. no.")+fld("donorSire","Donor's sire")+fld("donorDam","Donor's dam")+fld("sire","Sire")+fld("sireReg","Sire passport / reg. no."))+
 sec("Transfer",fld("recipient","Recipient mare")+fld("recipientReg","Recipient passport / reg. no.")+fld("transferDate","Transfer date",{type:"date"})+fld("expected","Expected foaling",{type:"date"})+fld("vet","Veterinarian",{wide:1}))+
 `<div class="sec"><h3>Pregnancy checks</h3>${listEd("checks",[{c:"date",label:"Date",date:1,w:"150px"},{c:"result",label:"Result",opts:Object.entries(CHK).map(([k,v])=>[k,v[0]]),w:"170px"},{c:"notes",label:"Notes",w:"220px"}],"Add check")}</div>`+
 sec("Notes",fld("notes","Notes (English)",{area:3,wide:1})+fld("notesAr","Notes (Arabic)",{area:3,wide:1,ar:1}));
ED.letter=()=>`<div class="ed-h"><h2>Custom Letter</h2>${langSeg()}</div><p class="hint">A letter on the official letterhead. Wrap words in **double stars** for bold.</p>`+
 sec("Letter",fld("ref","Reference no.")+fld("date","Date",{type:"date"})+fld("toEn","To (English)",{area:2})+fld("toAr","To (Arabic)",{area:2,ar:1})+fld("subjectEn","Subject (English)",{wide:1})+fld("subjectAr","Subject (Arabic)",{wide:1,ar:1}))+
 sec("Text",fld("bodyEn","Letter (English)",{area:10,wide:1})+fld("bodyAr","Letter (Arabic)",{area:10,wide:1,ar:1}))+
 sec("Signature",fld("signName","Signed by")+`<div></div>`+fld("signTitleEn","Title (English)")+fld("signTitleAr","Title (Arabic)",{ar:1}));

const NEWROW={embryos:()=>({date:today(),partner:"",method:"et",stage:"",grade:"",status:"frozen",recipient:"",storage:""}),plan:()=>({season:String(new Date().getFullYear()),partner:"",method:"natural",date:"",status:"planned",notes:""}),shows:()=>({date:"",show:"",cls:"",result:""}),progeny:()=>({name:"",year:"",sex:"filly",other:""}),covers:()=>({date:today()}),horses:()=>({name:"",sex:"mare",box:""}),clauses:()=>({tEn:"",bEn:"",tAr:"",bAr:""}),staff:()=>({name:"",nameAr:"",pos:"",posAr:"",basic:0,allow:0,ot:0,ded:0,adv:0}),entries:()=>({date:today(),type:"vacc",what:"",by:"",next:""}),rows:()=>({en:"",ar:"",qty:1,amt:0,type:"s",status:"pending"}),items:()=>CUR_DOC==="po"?{d:"",q:1,u:"",p:0}:CUR_DOC==="inv"?{d:"",q:1,p:0}:{name:"",s0:"",s1:"",s2:"",s3:"",s4:""},money:()=>({elEn:"",elAr:"",amEn:"",amAr:"",ptEn:"",ptAr:""}),checks:()=>({date:today(),result:"scheduled",notes:""})};

/* ---------- drawing ---------- */
const VIEW=CFG.mode==='view'||CFG.mode==='print';
function meta(){return{letterhead:LH,ref:CFG.ref||(CFG.numbered&&CFG.mode==='new'?(T.refPending||''):''),issued:CFG.issued?fmtDate(CFG.issued,(S[CUR_DOC].lang==='ar'?'ar':'en')):'',verifyUrl:CFG.verifyUrl||''}}
function context(){const c=Object.assign({},CFG.ctx||{});if(CUR_DOC==='rem')c.reminders=(c.reminders||[]).filter(x=>x.days<=(+S.rem.window||60));SK.setContext(c)}
function fit(){if(!canvas||CFG.mode==='print')return;host.style.zoom=1;const pg=host.querySelector('.page');if(!pg)return;const avail=canvas.clientWidth-(window.innerWidth<900?24:48);const z=Math.min(1,avail/pg.offsetWidth);host.style.zoom=z>0?z:1}
function drawPreview(){context();SK.render(host,CUR_DOC,S[CUR_DOC],meta());if(CFG.void)host.classList.add('doc-void');fit()}
function drawEditor(){if(!ed||!ED[CUR_DOC])return;const st=ed.scrollTop;ed.innerHTML=ED[CUR_DOC]();ed.scrollTop=st;
  ed.querySelectorAll('[data-k="no"],[data-k="ref"],[data-k="rno"]').forEach(i=>{if(!i.value)i.placeholder=T.refPh||'';if(CFG.mode==='template'){i.disabled=true}});
  if(!CFG.canEdit)ed.querySelectorAll('input,select,textarea,button:not([data-lang])').forEach(i=>{i.disabled=true});}
let tm;function soon(){clearTimeout(tm);tm=setTimeout(drawPreview,160)}
function redraw(){drawEditor();drawPreview()}

/* ---------- editing (same behaviour as the original Studio) ---------- */
if(ed&&!VIEW){
  ed.addEventListener('input',e=>{const t=e.target,d=S[CUR_DOC];
    if(t.dataset.k){d[t.dataset.k]=t.type==="number"?(t.value===""?"":+t.value):t.value;soon()}
    else if(t.dataset.l){const r=d[t.dataset.l][+t.dataset.i];r[t.dataset.c]=t.type==="number"?(t.value===""?"":+t.value):t.value;soon()}});
  ed.addEventListener('change',e=>{const t=e.target;
    if(t.dataset.inc){S.board.inc=S.board.inc||{};S.board.inc[t.dataset.inc]=t.checked?1:0;soon();return}
    if(t.dataset.idpick!==undefined){const n=t.dataset.idpick,P=S.idcard.pick;if(t.checked){if(!P.some(x=>low(x)===low(n)))P.push(n)}else S.idcard.pick=P.filter(x=>low(x)!==low(n));redraw();return}
    if(['cover','board','letters','inv','pay','po','fin'].includes(CUR_DOC)&&(t.dataset.k||t.dataset.l)){clearTimeout(tm);tm=setTimeout(()=>{drawEditor();drawPreview()},250)}});
  ed.addEventListener('click',e=>{const b=e.target.closest('button');if(!b||b.disabled)return;const d=S[CUR_DOC];
    if(b.dataset.lang){d.lang=b.dataset.lang;redraw();return}
    if(b.dataset.mode){d.mode=b.dataset.mode;redraw();return}
    if(b.dataset.ltype){d.type=b.dataset.ltype;redraw();return}
    const a=b.dataset.act;if(!a)return;
    if(a==='idAll'){d.pick=((CFG.ctx&&CFG.ctx.employees)||[]).filter(x=>x.status!=='left').map(x=>x.nameEn);redraw();return}
    if(a==='idNone'){d.pick=[];redraw();return}
    if(a==='photoDel'){d.photo='';redraw();return}
    if(a==='payClear'){d.staff.forEach(x=>{x.ot=0;x.ded=0;x.adv=0});redraw();return}
    if(a==='useInvTotal'){const sub=d.items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0);d.rAmount=Math.round((sub-n2(d.discount)-n2(d.paid))*100)/100;if(!d.rFor)d.rFor=(d.lang==='ar'?'الفاتورة رقم ':'Invoice ')+d.no;redraw();return}
    const L=d[b.dataset.l],i=+b.dataset.i;if(!L)return;
    if(a==='add')L.push(NEWROW[b.dataset.l]?NEWROW[b.dataset.l]():{});
    if(a==='del')L.splice(i,1);
    if(a==='up'&&i>0)[L[i-1],L[i]]=[L[i],L[i-1]];
    redraw();
    if(a==='add'){const ins=ed.querySelectorAll(`[data-l="${b.dataset.l}"][data-i="${L.length-1}"]`);if(ins[0])ins[0].focus()}});
}

/* ---------- toolbar: letterhead, record, save / issue ---------- */
const lhSel=$('#lhSel');if(lhSel){lhSel.value=String(LH);lhSel.addEventListener('change',()=>{LH=+lhSel.value;drawPreview()})}
const rec=$('#recPick');if(rec&&CFG.record&&CFG.record.base){rec.addEventListener('change',()=>{if(rec.value)location.href=CFG.record.base+encodeURIComponent(rec.value)})}
function post(url,fields){const f=document.createElement('form');f.method='post';f.action=url;f.hidden=true;
  Object.entries(Object.assign({_csrf:csrf},fields)).forEach(([k,v])=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=v==null?'':String(v);f.appendChild(i)});document.body.appendChild(f);f.submit()}
const saveBtn=$('#saveBtn');if(saveBtn)saveBtn.addEventListener('click',()=>{
  if(CFG.mode==='new'&&saveBtn.dataset.confirm&&!confirm(saveBtn.dataset.confirm))return;
  saveBtn.disabled=true;
  post(CFG.saveUrl,{data:JSON.stringify(S[CUR_DOC]),lang:S[CUR_DOC].lang||CFG.lang,letterhead:LH,record_type:(CFG.record&&CFG.record.id)?CFG.record.kind:'',record_id:(CFG.record&&CFG.record.id)||''})});

/* ---------- print, PDF and share (each one is recorded in the Activity Log) ---------- */
function log(action,via){const url=CFG.id?CFG.logUrl:CFG.logUrl;const fd=new FormData();fd.append('_csrf',csrf);fd.append('action',action);if(via)fd.append('via',via);if(!CFG.id)fd.append('type',CUR_DOC);
  try{fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})}catch(e){}}
function toast(t,bad){let el=$('#studioToast');if(!el){el=document.createElement('div');el.id='studioToast';el.className='studio-toast';document.body.appendChild(el)}el.textContent=t;el.classList.toggle('bad',!!bad);el.classList.add('on');clearTimeout(toast.t);toast.t=setTimeout(()=>el.classList.remove('on'),4200)}
async function makePdf(btn){
  if(!window.htmlToImage||!window.jspdf)throw new Error('libs');
  const old=btn?btn.textContent:'';if(btn){btn.disabled=true;btn.textContent=T.preparing||'…'}
  host.style.zoom=1;
  try{if(document.fonts)await document.fonts.ready;
    const pages=[...host.querySelectorAll('.page')];const fontCSS=await htmlToImage.getFontEmbedCSS(pages[0]);
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
const pdfBtn=$('#pdfBtn');if(pdfBtn)pdfBtn.addEventListener('click',async()=>{try{download(await makePdf(pdfBtn));log('download')}catch(e){console.error(e);toast(T.pdfFailed||'PDF failed',1)}});
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
drawPreview();
if(document.fonts)document.fonts.ready.then(drawPreview);
if(CFG.mode==='print'){window.addEventListener('load',()=>setTimeout(()=>{log('print');window.print()},500))}
window.SKEditor={get data(){return S[CUR_DOC]},redraw,makePdf};
})();
