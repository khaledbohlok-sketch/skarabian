/*
 * SK Arabian Studio — document renderer (A4 letterhead pages).
 * Ported from the stud's original "SK Arabian Studio" so every template keeps exactly the same layout and wording.
 * Data now comes from the SK Arabians Management System (linked records) instead of the browser's storage.
 * Exposes window.SKStudio.
 */
(function(){
const CFG=window.SK_STUDIO_CFG||{};
const LOGO=CFG.logo||"",MARK=CFG.mark||"";
const LH=CFG.lh||{cr:"231961",mob:"5536 6699",email:"sk.qa@hotmail.com",pobox:"6657, Doha - Qatar"};
let CTX={};

/* ---------- helpers ---------- */
const $=s=>document.querySelector(s);
const esc=v=>String(v??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
const rich=v=>esc(v).replace(/\*\*(.+?)\*\*/g,"<b>$1</b>");
const n2=v=>{const x=parseFloat(v);return isNaN(x)?0:x};
const money=v=>n2(v).toLocaleString("en-US",{minimumFractionDigits:2,maximumFractionDigits:2});
const int=v=>n2(v).toLocaleString("en-US",{maximumFractionDigits:2});
const MEN=["January","February","March","April","May","June","July","August","September","October","November","December"];
const MAR=["يناير","فبراير","مارس","أبريل","مايو","يونيو","يوليو","أغسطس","سبتمبر","أكتوبر","نوفمبر","ديسمبر"];
function pd(v){const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(v||"");return m?{y:+m[1],m:+m[2],d:+m[3]}:null}
function fmtDate(v,lang,pad){const p=pd(v);if(!p)return v||"";const d=pad?String(p.d).padStart(2,"0"):p.d;return lang==="ar"?`${d} ${MAR[p.m-1]} ${p.y}`:`${d} ${MEN[p.m-1]} ${p.y}`}
function slashDate(v){const p=pd(v);return p?`${String(p.d).padStart(2,"0")} / ${String(p.m).padStart(2,"0")} / ${p.y}`:(v||"")}
function today(){const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,"0")}-${String(d.getDate()).padStart(2,"0")}`}

/* number to words */
const EA=["","One","Two","Three","Four","Five","Six","Seven","Eight","Nine","Ten","Eleven","Twelve","Thirteen","Fourteen","Fifteen","Sixteen","Seventeen","Eighteen","Nineteen"];
const ET=["","","Twenty","Thirty","Forty","Fifty","Sixty","Seventy","Eighty","Ninety"];
function en999(n){let s="";if(n>=100){s+=EA[Math.floor(n/100)]+" Hundred";n%=100;if(n)s+=" "}if(n>=20){s+=ET[Math.floor(n/10)];if(n%10)s+="-"+EA[n%10]}else if(n)s+=EA[n];return s}
function enWords(n){n=Math.floor(n);if(!n)return "Zero";const p=[];for(const[v,nm] of [[1e9,"Billion"],[1e6,"Million"],[1e3,"Thousand"]]){if(n>=v){p.push(en999(Math.floor(n/v))+" "+nm);n%=v}}if(n)p.push(en999(n));return p.join(" ")}
const AO=["","واحد","اثنان","ثلاثة","أربعة","خمسة","ستة","سبعة","ثمانية","تسعة","عشرة","أحد عشر","اثنا عشر","ثلاثة عشر","أربعة عشر","خمسة عشر","ستة عشر","سبعة عشر","ثمانية عشر","تسعة عشر"];
const AT=["","","عشرون","ثلاثون","أربعون","خمسون","ستون","سبعون","ثمانون","تسعون"];
const AH=["","مائة","مائتان","ثلاثمائة","أربعمائة","خمسمائة","ستمائة","سبعمائة","ثمانمائة","تسعمائة"];
function ar99(n){return n<20?AO[n]:(n%10?AO[n%10]+" و"+AT[Math.floor(n/10)]:AT[Math.floor(n/10)])}
function ar999(n){const p=[],h=Math.floor(n/100),r=n%100;if(h)p.push(AH[h]);if(r)p.push(ar99(r));return p.join(" و")}
function arScale(n,one,two,pl,sg){return n===1?one:n===2?two:(n<=10?ar999(n)+" "+pl:ar999(n)+" "+sg)}
function arWords(n){n=Math.floor(n);if(!n)return "صفر";const p=[];const m=Math.floor(n/1e6),k=Math.floor(n%1e6/1e3),r=n%1e3;if(m)p.push(arScale(m,"مليون","مليونان","ملايين","مليون"));if(k)p.push(arScale(k,"ألف","ألفان","آلاف","ألف"));if(r)p.push(ar999(r));return p.join(" و")}
const CUR={QAR:{en:"Qatari Riyals",sub:"Dirhams",ar:"ريال قطري",subAr:"درهم",code:"QAR",arCode:"ر.ق"},EUR:{en:"Euros",sub:"Cents",ar:"يورو",subAr:"سنت",code:"EUR",arCode:"يورو"},USD:{en:"US Dollars",sub:"Cents",ar:"دولار أمريكي",subAr:"سنت",code:"USD",arCode:"دولار"},GBP:{en:"Pounds Sterling",sub:"Pence",ar:"جنيه إسترليني",subAr:"بنس",code:"GBP",arCode:"جنيه"},SAR:{en:"Saudi Riyals",sub:"Halalas",ar:"ريال سعودي",subAr:"هللة",code:"SAR",arCode:"ر.س"},AED:{en:"UAE Dirhams",sub:"Fils",ar:"درهم إماراتي",subAr:"فلس",code:"AED",arCode:"د.إ"},KWD:{en:"Kuwaiti Dinars",sub:"Fils",ar:"دينار كويتي",subAr:"فلس",code:"KWD",arCode:"د.ك"},BHD:{en:"Bahraini Dinars",sub:"Fils",ar:"دينار بحريني",subAr:"فلس",code:"BHD",arCode:"د.ب"},OMR:{en:"Omani Rials",sub:"Baisa",ar:"ريال عماني",subAr:"بيسة",code:"OMR",arCode:"ر.ع"},JOD:{en:"Jordanian Dinars",sub:"Fils",ar:"دينار أردني",subAr:"فلس",code:"JOD",arCode:"د.أ"},EGP:{en:"Egyptian Pounds",sub:"Piastres",ar:"جنيه مصري",subAr:"قرش",code:"EGP",arCode:"ج.م"}};
function splitAmt(v){const x=Math.round(n2(v)*100);return[Math.floor(x/100),x%100]}
function wordsEn(v,c,only=true){const C=CUR[c]||CUR.QAR,[i,f]=splitAmt(v);return enWords(i)+" "+C.en+(f?" and "+enWords(f)+" "+C.sub:"")+(only?" only":"")}
function wordsAr(v,c,only=true){const C=CUR[c]||CUR.QAR,[i,f]=splitAmt(v);return arWords(i)+" "+C.ar+(f?" و"+arWords(f)+" "+C.subAr:"")+(only?" فقط لا غير":"")}

/* ---------- sample data ---------- */
const FIN_ROWS=[["Al Bidda","البدع",1,11416.65,"s"],["Al Brth","البرث",3,12670,"s"],["Al Maydan","الميدان",1,16340,"s"],["Al Rayyan","الريان",1,2340,"s"],["Arsan International","أرسان إنترناشونال",1,5534,"s"],["EVMC","EVMC",1,3850,"s"],["First Vet For Veterinary Services W.L.L","فيرست فيت للخدمات البيطرية ذ.م.م",2,3568,"s"],["Le Roux","لو رو",1,4000,"s"],["Qatar Racing And Equestrian Club","نادي قطر للسباق والفروسية",1,10000,"s"],["Vet Zone","فت زون",1,5260,"s"],["Barik","بارق",1,10940,"s"],["Arabian Insider","أرابيان إنسايدر",1,20175,"s"],["Arabian Essence","أرابيان إسنس",1,41369,"s"],["Rafeek + Jennifer","رفيق + جينيفر",1,13697,"s"],["Khaled + Bahaa Salary","راتب خالد + بهاء",1,2920,"w"],["Grooms Salary","رواتب السيّاس",1,15600,"w"],["Lebanon Event","فعالية لبنان",1,1000,"s"],["Fabricio Salary","راتب فابريسيو",1,12800,"w"],["Sawdust","نشارة الخشب",1,8000,"s"],["Maraba Al Asayel","مرابع الأصايل",1,3963.90,"s"],["The Vets Experts","ذا فتس إكسبرتس",1,10500,"s"],["Almalaky Public Kitchens","المالكي للمطابخ العامة",1,2500,"s"],["Uber","أوبر",1,59,"s"],["Ooredoo","Ooredoo",1,140,"s"],["Al Bidda","البدع",1,560,"s"],["Buzwair Industrial Gases Factories","مصانع بوزوير للغازات الصناعية",1,360,"s"],["Maraba Al Asayel","مرابع الأصايل",1,5810.74,"s"]].map(r=>({en:r[0],ar:r[1],qty:r[2],amt:r[3],type:r[4],status:"pending"}));

const FEED=["PROBREED MIX","FIBERFORCE","VITAMINO","WHOLEGAIN","MASH & MIX","SHINE & SHOW","CEN OIL","MIRCOVITAL","POWER BOOSTER","DOUBLE STRENGTH FORMULA","ACTION MIX","BROODMARE AND GROWING","Other Supplements","Meds"];

const OFFER_CLAUSES=[
 {tEn:"Position & Administrative Powers",bEn:"The General Manager shall assume **full management** of SK Arabian for Trading and shall have **all powers necessary** to conduct the business of the establishment and to take the administrative and executive decisions relating thereto.",
  tAr:"المنصب والصلاحيات الإدارية",bAr:"يتولى المدير العام **الإدارة الكاملة** لـ«اس كي ارابيان للتجارة»، وتكون له **كافة الصلاحيات اللازمة** لتسيير أعمال المؤسسة واتخاذ القرارات الإدارية والتنفيذية المتعلقة بها."},
 {tEn:"Duties & Responsibilities",bEn:"The General Manager shall be responsible for the **development of the Stud**, the **selection of qualified horses** for participation in shows and championships, and the **management of breeding programmes**, with all decisions subject to review and approval by {owner}.",
  tAr:"المهام والمسؤوليات",bAr:"يتولى المدير العام مسؤولية **تطوير المربط**، و**اختيار الخيول المؤهلة** للمشاركة في العروض والبطولات، و**إدارة برامج التربية**، مع خضوع كافة القرارات للمراجعة والاعتماد من {owner}."},
 {tEn:"Trading, Sales & Purchases",bEn:"Trading, sale and purchase transactions of the Stud are carried out **with the approval of the General Manager, {hon} {name}, and the approval of {owner}**.",
  tAr:"عمليات التداول والبيع والشراء",bAr:"عمليات التداول والبيع والشراء الخاصة بالمربط تتم **بموافقة المدير العام، {hon} / {name}، والاعتماد من {owner}**."},
 {tEn:"Contract Term & Renewal",bEn:"The term of this contract is **one calendar year** commencing from the date of starting work, and it shall renew automatically for a similar period, unless either party notifies the other party in writing of its wish not to renew at least **two months** before the end of the contract term.",
  tAr:"مدة العقد والتجديد",bAr:"مدة هذا العقد **سنة ميلادية واحدة** تبدأ من تاريخ مباشرة العمل، وتتجدد تلقائيًا لمدة مماثلة، ما لم يُخطر أحد الطرفين الطرفَ الآخر كتابيًا برغبته في عدم التجديد قبل انتهاء مدة العقد بـ**شهرين على الأقل**."},
 {tEn:"Monthly Salary & Direct Entitlements",bEn:"The General Manager shall be entitled to a total gross monthly salary of **{salary}, i.e. {salaryWords}, equivalent to {salaryQar}**, payable at the end of each calendar month in accordance with the payroll system of the Stud.",
  tAr:"الراتب الشهري والمستحقات المباشرة",bAr:"يستحق المدير العام راتبًا شهريًا إجماليًا قدره **{salary}، أي {salaryWords}، بما يعادل {salaryQar}** يُصرف في نهاية كل شهر ميلادي وفقًا لنظام الرواتب المعمول به في المربط."},
 {tEn:"Incentives & Profit Share",bEn:"In recognition of the efforts exerted and the results achieved, the General Manager shall be entitled to:\n1. **Ten percent, 10%,** of the net profits derived from prize money awarded in championships and competitions won by the horses of the Stud.\n2. The General Manager, **{hon} {name}**, is entitled to **10%** of the net profits realised from the sale of horses purchased from abroad, in addition to **10%** of the total profits from horses bred and cared for under her direct supervision.\nThese percentages shall be paid **only after the amounts have actually been collected** by the Stud.",
  tAr:"الحوافز ونسبة الأرباح",bAr:"تقديرًا للجهود المبذولة والنتائج المحققة، يستحق المدير العام ما يلي:\n1. نسبة **عشرة بالمائة 10%** من صافي الأرباح المحققة من الجوائز المالية للبطولات والمسابقات التي تفوز بها خيول المربط.\n2. يحق للمدير العام، **{hon} {name}**، الحصول على نسبة **10%** من صافي الأرباح المحققة من عمليات بيع الخيول المشتراة من الخارج، بالإضافة إلى نسبة **10%** من إجمالي الأرباح للخيول التي تتم تربيتها ورعايتها تحت إشرافها المباشر.\nوتُصرف هذه النسب **بعد تحصيل المبالغ فعليًا** من قِبل المربط."},
 {tEn:"Official Travel & Accommodation",bEn:"The Stud shall bear all travel and accommodation costs of the General Manager for official duties, in addition to **all fees and costs of her residency in the State of Qatar**. Air travel shall be in **Business Class** and accommodation in **five-star hotels**, with local and international transport expenses covered.",
  tAr:"السفر والإقامة الرسمية",bAr:"يتحمّل المربط كافة تكاليف سفر وإقامة المدير العام الخاصة بمهام العمل الرسمية، إضافةً إلى **كافة رسوم وتكاليف إقامته في دولة قطر**، على أن تكون تذاكر الطيران على **درجة رجال الأعمال** والإقامة في **فنادق من فئة الخمس نجوم**، مع تغطية نفقات التنقل المحلي والدولي."},
 {tEn:"Transparency, Privacy & Interest of the Stud",bEn:"The General Manager **and the Owner** shall keep **all Stud data and information strictly confidential**, shall not disclose it to any third party, and shall act **transparently** with each other in the **best interest of the Stud**. This obligation applies during and after the term of the contract.",
  tAr:"الشفافية والخصوصية ومصلحة المربط",bAr:"يلتزم المدير العام **والمالك بالسرية التامة** لجميع بيانات ومعلومات المربط، وعدم الإفصاح عنها لأي طرف ثالث، **وبالعمل بشفافية** فيما بينهما بما يحقق **مصلحة المربط**. ويسري هذا الالتزام خلال مدة العقد وبعد انتهائه."}
];
const OFFER_MONEY=[
 {elEn:"Total Monthly Salary",elAr:"الراتب الشهري الإجمالي",amEn:"**{salary} / month**\nequivalent to {salaryQarShort}",amAr:"**{salary} شهريًا**\nبما يعادل {salaryQarShort}",ptEn:"End of each calendar month",ptAr:"في نهاية كل شهر ميلادي"},
 {elEn:"Profit Share — Prize Money",elAr:"حصة أرباح جوائز البطولات",amEn:"**10% of net profit**",amAr:"**10% من صافي الربح**",ptEn:"Pursuant to Clause 6",ptAr:"وفقًا للبند السادس"},
 {elEn:"Profit Share — Imported Horse Sales",elAr:"حصة أرباح بيع الخيول المستوردة",amEn:"**10% of net profit**",amAr:"**10% من صافي الربح**",ptEn:"Pursuant to Clause 6",ptAr:"وفقًا للبند السادس"},
 {elEn:"Profit Share — Horses Bred",elAr:"حصة أرباح الخيول المرباة",amEn:"**10% of total profit**",amAr:"**10% من إجمالي الربح**",ptEn:"Pursuant to Clause 6",ptAr:"وفقًا للبند السادس"}
];

function defaults(){return{
 fin:{lang:"en",date:"2026-10-04",currency:"QAR",titleEn:"Outstanding Payables Statement",titleAr:"كشف المستحقات غير المسددة",subEn:"Summary of all supplier invoices, salaries and expenses currently pending payment.",subAr:"ملخص لجميع فواتير الموردين والرواتب والمصروفات المستحقة وبانتظار السداد.",prepared:"",approved:"",rows:FIN_ROWS.map(r=>({...r}))},
 diet:{lang:"en",date:"",horse:"MEERA AL MASHRAB",dob:"Jan 29, 2021",sire:"Dominic M (US)",dam:"Majalina (US)",preg:"",box:"",working:"",work:"",notes:"",recorded:"",checked:"",
   slots:[{en:"Early Morning",ar:"الصباح الباكر"},{en:"Late Morning",ar:"آخر الصباح"},{en:"Afternoon",ar:"بعد الظهر"},{en:"Evening",ar:"المساء"},{en:"Late Evening",ar:"آخر المساء"}],
   items:FEED.map(n=>({name:n,s0:"",s1:"",s2:"",s3:"",s4:""}))},
 po:{lang:"en",no:"SK/PO/2026/001",date:today(),delivery:"",currency:"QAR",supplier:"Supplier name",contact:"",phone:"",email:"",shipTo:"SK Arabian Stud, Doha – Qatar",payment:"Cash on delivery",ref:"",discount:0,
   items:[{d:"Wood shavings bedding",q:100,u:"Bale",p:80},{d:"Probreed Mix (20 kg bag)",q:40,u:"Bag",p:95},{d:"Fiberforce (20 kg bag)",q:20,u:"Bag",p:110}],
   termsAr:"1. يرجى ذكر رقم أمر الشراء على الفاتورة وإشعار التسليم.\n2. تخضع البضاعة للفحص عند التسليم في المربط.\n3. الأسعار ثابتة للأصناف والكميات المذكورة أعلاه.",terms:"1. Please quote the PO number on your invoice and delivery note.\n2. Goods are subject to inspection on delivery at the stud.\n3. Prices are fixed for the items and quantities above.",requested:"",approved:""},
 sal:{date:"2026-10-02",gender:"m",toEn:"Commercial Bank of Qatar",toAr:"البنك التجاري القطري",cityEn:"Doha – Qatar",cityAr:"الدوحة – قطر",nameEn:"Rafeek Hussein Bohlok",nameAr:"رفيق حسين بحلق",natEn:"Lebanese",natAr:"لبناني",qid:"30542200506",qidExp:"08/09/2027",posEn:"Purchasing Representative",posAr:"مندوب مشتريات",sinceEn:"February 2026",sinceAr:"فبراير 2026",salary:7000,signName:"Mr. Salem Khalaf A A AlMannai"},
 offer:{lang:"en",ref:"SK/HR/OF/2026/089",date:"2026-10-02",gender:"f",honEn:"Ms.",honAr:"السيدة",nameEn:"Claudia Darius",nameAr:"كلوديا داريوس",natEn:"German",natAr:"ألمانية",passport:"C764Y25JC",posEn:"General Manager",posAr:"المدير العام",
   salAmt:12000,salCur:"EUR",salQar:49142,
   greetEn:"Dear {hon} {name},  Greetings,",greetAr:"{hon} الفاضلة / {name} المحترمة، تحية طيبة وبعد،",
   introEn:"The Management of SK Arabian for Trading, C.R. No. 231961, has the pleasure of extending to you this official offer of employment for the position of {position}, in recognition of your qualifications and experience in the administration of Arabian horse studs. This offer is made subject to the following terms and conditions:",
   introAr:"يسرّ إدارة اس كي ارابيان للتجارة، سجل تجاري رقم 231961، أن تتقدّم إليكم بهذا العرض الوظيفي الرسمي لشغل منصب {position}، تقديرًا لمؤهلاتكم وخبراتكم في مجال إدارة مرابط الخيول العربية الأصيلة، وذلك وفقًا للبنود والشروط الآتية:",
   tableAfter:5,clauses:OFFER_CLAUSES.map(c=>({...c})),money:OFFER_MONEY.map(m=>({...m})),
   ownerEn:"Mr. Hamad Khalaf A A Al-Mannai",ownerAr:"السيد / حمد خلف أحمد آل سالم المناعي",ownerShortEn:"Mr. Hamad Khalaf Al-Mannai",ownerShortAr:"السيّد حمد خلف المناعي",ownerQid:"28663400213",ownerTitleEn:"Owner & Authorised Signatory",ownerTitleAr:"المالك والمفوّض بالتوقيع"},
 transfer:{lang:"en",no:"SK/TR/2026/001",date:today(),transferDate:today(),hName:"MEERA AL MASHRAB",breed:"Purebred Arabian",breedAr:"عربي أصيل",sex:"mare",colourEn:"Grey",colourAr:"رمادي",dob:"Jan 29, 2021",sire:"Dominic M (US)",dam:"Majalina (US)",chip:"",passport:"",origin:"",
   sNameEn:"SK Arabian for Trading",sNameAr:"اس كي ارابيان للتجارة",sId:"C.R. 231961",sNat:"Qatar",sPhone:"5536 6699",bNameEn:"Buyer name",bNameAr:"اسم المشتري",bId:"",bNat:"",bPhone:"",
   showPrice:"yes",price:"",currency:"QAR",payStatus:"paid",witness:"",
   declEn:"We, the undersigned, confirm that ownership of the horse described above has been transferred from the Seller to the Buyer on {transferDate}. The Seller confirms that the horse is free of any claims, liens or disputes, and the Buyer accepts the horse in its present condition. From the date of transfer, the Buyer takes full responsibility for the horse, including its care, insurance and registration with the relevant authorities.",
   declAr:"نقرّ نحن الموقّعين أدناه بأنه قد تم نقل ملكية الخيل الموصوف أعلاه من البائع إلى المشتري بتاريخ {transferDate}. ويقرّ البائع بأن الخيل خالٍ من أي مطالبات أو رهون أو نزاعات، ويقبل المشتري الخيل بحالته الراهنة. ويتحمّل المشتري اعتبارًا من تاريخ النقل كامل المسؤولية عن الخيل، بما في ذلك رعايته وتأمينه وتسجيله لدى الجهات المختصة."},
 inv:{lang:"en",mode:"invoice",no:"SK/INV/2026/001",rno:"SK/RC/2026/001",date:today(),due:"",currency:"QAR",cName:"Customer name",cPhone:"",cEmail:"",cAddr:"",
   items:[{d:"Monthly boarding – October 2026",q:1,p:3500},{d:"Farrier service",q:1,p:250}],discount:0,paid:0,
   payInfo:"Payment by cash, cheque or bank transfer to SK Arabian for Trading.",payInfoAr:"الدفع نقدًا أو بشيك أو بتحويل بنكي إلى اس كي ارابيان للتجارة.",
   rAmount:"",rFor:"",rMethod:"cash",rRef:"",rBank:"",receivedBy:""},
 pay:{lang:"en",month:"2026-10",payDate:"",method:"Bank transfer",summary:"yes",prepared:"",
   staff:[{name:"Rafeek Hussein Bohlok",nameAr:"رفيق حسين بحلق",pos:"Purchasing Representative",posAr:"مندوب مشتريات",basic:7000,allow:0,ot:0,ded:0,adv:0},
          {name:"Groom name (example)",nameAr:"اسم السائس",pos:"Groom",posAr:"سائس",basic:1500,allow:300,ot:0,ded:0,adv:0}]},
 vet:{lang:"en",horse:"MEERA AL MASHRAB",sex:"mare",dob:"Jan 29, 2021",sire:"Dominic M (US)",dam:"Majalina (US)",chip:"",box:"",notes:"",
   entries:[{date:"2026-04-10",type:"vacc",what:"Equine influenza + tetanus (example)",by:"Vet",next:"2026-10-10"},{date:"2026-08-01",type:"worm",what:"Ivermectin (example)",by:"Stable",next:"2026-11-01"},{date:"2026-09-05",type:"farr",what:"Trim, front shoes (example)",by:"Farrier",next:"2026-10-03"}]},
 reg:{filter:"all",q:""},
 horses:{sel:0,q:""},staff:{sel:0,q:""},
 idcard:{output:"card",pick:[],issue:"",expiry:"",showQid:"yes",title:"STAFF ID",titleAr:"بطاقة موظف"},
 profile:{lang:"en",nameEn:"MEERA AL MASHRAB",nameAr:"ميرا المشرب",sex:"mare",colourEn:"Grey",colourAr:"رمادي",dob:"Jan 29, 2021",breed:"Purebred Arabian",breedAr:"عربي أصيل",breeder:"",owner:"SK Arabian Stud",origin:"",chip:"",passport:"",height:"",status:"stud",box:"",photo:"",
   p_s:"Dominic M (US)",p_d:"Majalina (US)",p_ss:"",p_sd:"",p_ds:"",p_dd:"",p_sss:"",p_ssd:"",p_sds:"",p_sdd:"",p_dss:"",p_dsd:"",p_dds:"",p_ddd:"",p_ssss:"",p_sssd:"",p_ssds:"",p_ssdd:"",p_sdss:"",p_sdsd:"",p_sdds:"",p_sddd:"",p_dsss:"",p_dssd:"",p_dsds:"",p_dsdd:"",p_ddss:"",p_ddsd:"",p_ddds:"",p_dddd:"",
   shows:[],progeny:[],embryos:[],plan:[],notesEn:"",notesAr:""},
 rem:{window:"60"},
 cover:{lang:"en",no:"SK/CV/2026/001",date:today(),season:"2026",mare:"MEERA AL MASHRAB",mareDob:"Jan 29, 2021",mareReg:"",mareChip:"",mareSire:"Dominic M (US)",mareDam:"Majalina (US)",
   ownerEn:"SK Arabian for Trading",ownerAr:"اس كي ارابيان للتجارة",ownerPhone:"5536 6699",stallion:"Stallion name",stallionReg:"",stallionSire:"",stallionDam:"",stOwnerEn:"SK Arabian for Trading",stOwnerAr:"اس كي ارابيان للتجارة",
   method:"natural",covers:[{date:"2026-03-02"},{date:"2026-03-04"}],gest:340,checkDate:"",checkResult:"none",vet:"",notes:""},
 board:{lang:"en",ref:"SK/BA/2026/001",date:today(),ownerEn:"Owner name",ownerAr:"اسم المالك",ownerId:"",ownerPhone:"",horses:[{name:"Horse name",sex:"mare",box:""}],
   start:today(),term:12,fee:3500,currency:"QAR",dueDay:5,deposit:0,notice:30,inc:{feed:1,bedding:1,turnout:1,grooming:1,exercise:0,farrier:0,vet:0},
   extrasEn:"Vet treatment, medication, farrier work, transport and show entries are charged separately at cost.",extrasAr:"تُحتسب العلاجات البيطرية والأدوية وأعمال الحدادة والنقل ورسوم البطولات بشكل منفصل حسب التكلفة.",
   clauses:[
    {tEn:"Term",bEn:"This agreement starts on {start} for {term} months, ending on {end}, and renews automatically for the same period unless either party gives written notice at least {notice} days before the end date.",tAr:"المدة",bAr:"تبدأ هذه الاتفاقية بتاريخ {start} لمدة {term} شهرًا وتنتهي بتاريخ {end}، وتتجدد تلقائيًا لمدة مماثلة ما لم يُخطر أحد الطرفين الآخر كتابيًا قبل {notice} يومًا على الأقل من تاريخ الانتهاء."},
    {tEn:"Fees",bEn:"The Owner pays a monthly boarding fee of **{fee}** per horse, payable in advance by day {dueDay} of each month.",tAr:"الرسوم",bAr:"يدفع المالك رسوم إقامة شهرية قدرها **{fee}** عن كل خيل، تُدفع مقدمًا في موعد أقصاه يوم {dueDay} من كل شهر."},
    {tEn:"Deposit",bEn:"A refundable deposit of {deposit} is paid on signing and returned at the end of the agreement after settling any amounts due.",tAr:"التأمين",bAr:"يُدفع تأمين مسترد قدره {deposit} عند التوقيع، ويُعاد عند انتهاء الاتفاقية بعد تسوية أي مبالغ مستحقة."},
    {tEn:"Care",bEn:"The Stud will care for the horse(s) with reasonable skill and will tell the Owner promptly about any illness or injury. In an emergency, the Stud may call a vet without prior approval, at the Owner's cost.",tAr:"الرعاية",bAr:"يتولى المربط رعاية الخيل بعناية معقولة، ويبلغ المالك فورًا بأي مرض أو إصابة. وفي الحالات الطارئة يحق للمربط استدعاء الطبيب البيطري دون موافقة مسبقة وعلى نفقة المالك."},
    {tEn:"Late payment",bEn:"If fees remain unpaid for more than 30 days, the Stud may end this agreement with written notice, and the Owner must collect the horse(s) after settling all amounts due.",tAr:"التأخر في السداد",bAr:"إذا تأخر سداد الرسوم لأكثر من 30 يومًا، يحق للمربط إنهاء هذه الاتفاقية بإشعار كتابي، وعلى المالك استلام الخيل بعد سداد كافة المبالغ المستحقة."},
    {tEn:"Insurance & liability",bEn:"The Owner is responsible for insuring the horse(s). The Stud is not liable for illness, injury or death that is not caused by its negligence.",tAr:"التأمين والمسؤولية",bAr:"يتحمّل المالك مسؤولية التأمين على الخيل، ولا يتحمّل المربط أي مسؤولية عن المرض أو الإصابة أو النفوق ما لم يكن ناتجًا عن إهماله."}]},
 letters:{lang:"en",type:"exp",ref:"SK/HR/LT/2026/001",date:today(),gender:"m",nameEn:"Rafeek Hussein Bohlok",nameAr:"رفيق حسين بحلق",natEn:"Lebanese",natAr:"لبناني",qid:"30542200506",qidExp:"08/09/2027",posEn:"Purchasing Representative",posAr:"مندوب مشتريات",joinDate:"2026-02-01",endDate:"",basic:7000,
   toEn:"",toAr:"",purposeEn:"obtaining a Qatari driving licence",purposeAr:"حصوله على رخصة قيادة قطرية",leaveType:"annual",from:"",to:"",reason:"",replacement:"",contact:"",
   eosReason:"resignation",dpy:21,leaveDays:0,unpaid:0,other:0,deductions:0,signName:"Mr. Salem Khalaf A A AlMannai"}
}}
const clone=o=>JSON.parse(JSON.stringify(o));
const low=v=>String(v||"").trim().toLowerCase();
function uniqBy(a,k){const m=new Map();a.forEach(x=>{const y=k(x);if(y&&!m.has(y))m.set(y,x)});return[...m.values()]}
const sortBy=k=>(a,b)=>String(a[k]).localeCompare(String(b[k]));
const LTYPE={exp:["Experience Certificate","شهادة خبرة"],noc:["No Objection Certificate","شهادة عدم ممانعة"],leave:["Leave Request","طلب إجازة"],eos:["End of Service Settlement","مخالصة نهاية الخدمة"]};
const toISO=v=>{v=String(v||"").trim();if(/^\d{4}-\d{2}-\d{2}$/.test(v))return v;const m=/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/.exec(v);return m?`${m[3]}-${m[2].padStart(2,"0")}-${m[1].padStart(2,"0")}`:""};
const addDays=(iso,n)=>{const d=new Date(iso+"T00:00:00Z");if(!iso||isNaN(d))return"";d.setUTCDate(d.getUTCDate()+(+n||0));return d.toISOString().slice(0,10)};
const addMonths=(iso,n)=>{const d=new Date(iso+"T00:00:00Z");if(!iso||isNaN(d))return"";d.setUTCMonth(d.getUTCMonth()+(+n||0));return d.toISOString().slice(0,10)};
const dayDiff=(a,b)=>Math.round((new Date(b+"T00:00:00Z")-new Date(a+"T00:00:00Z"))/864e5);
function ymd(a,b){if(!a||!b||b<a)return{y:0,m:0,d:0};let y=0,m=0,cur=a;while(addMonths(a,(y+1)*12)<=b)y++;cur=addMonths(a,y*12);while(addMonths(cur,m+1)<=b)m++;cur=addMonths(cur,m);return{y,m,d:dayDiff(cur,b)}}
function fmtMonth(v,lang){const m=/^(\d{4})-(\d{2})$/.exec(v||"");if(!m)return v||"";return lang==="ar"?`${MAR[+m[2]-1]} ${m[1]}`:`${MEN[+m[2]-1]} ${m[1]}`}
function nextMonth(v){const m=/^(\d{4})-(\d{2})$/.exec(v||"");if(!m)return v;let y=+m[1],mo=+m[2]+1;if(mo>12){mo=1;y++}return `${y}-${String(mo).padStart(2,"0")}`}
function nextNo(v){return String(v||"").replace(/(\d+)(?!.*\d)/,m=>String(+m+1).padStart(m.length,"0"))}
const NEWKEEP_ALL=["lang","currency","method","mode","type","summary","showPrice","rMethod","dpy","gest","term","notice","dueDay","tableAfter","season","inc","slots","titleEn","titleAr","subEn","subAr","terms","termsAr","payInfo","payInfoAr","greetEn","greetAr","introEn","introAr","clauses","money","declEn","declAr","extrasEn","extrasAr","breed","breedAr","signName","cityEn","cityAr","shipTo","payment","prepared","approved","requested"];
const NEWKEEP={transfer:["sNameEn","sNameAr","sId","sNat","sPhone"],offer:["ownerEn","ownerAr","ownerShortEn","ownerShortAr","ownerQid","ownerTitleEn","ownerTitleAr","salCur"]};
const NEWTODAY=["date","transferDate","start"];
function cleanNew(doc,prev){const D=defaults()[doc],keep=new Set([...NEWKEEP_ALL,...(NEWKEEP[doc]||[])]),o={};
  for(const k in D){if(k[0]==="_")continue;if(keep.has(k)&&k in prev){o[k]=clone(prev[k]);continue}
    const v=D[k];if(NEWTODAY.includes(k))o[k]=today();else if(Array.isArray(v))o[k]=[];else if(v&&typeof v==="object")o[k]=clone(v);else if(typeof v==="number")o[k]="";else o[k]=""}
  ["no","ref","rno"].forEach(k=>{if(k in D)o[k]=nextNo(prev[k]||D[k])});
  if("gender" in D)o.gender=D.gender;if("sex" in D)o.sex=D.sex;if("checkResult" in D)o.checkResult="none";if("payStatus" in D)o.payStatus="paid";if("leaveType" in D)o.leaveType="annual";if("eosReason" in D)o.eosReason="resignation";
  if(doc==="cover")o.covers=[{date:today()}];if(doc==="board")o.horses=[{name:"",sex:"mare",box:""}];if(doc==="po"||doc==="inv")o.items=[];if(doc==="fin")o.rows=[];if(doc==="diet")o.items=[];if(doc==="vet")o.entries=[];
  return o}
const R={};
R.fin=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,C=CUR[d.currency]||CUR.QAR,cc=ar?C.arCode:C.code;
  const rows=d.rows.filter(r=>r.en||r.ar||n2(r.amt));
  const total=rows.reduce((s,r)=>s+n2(r.amt),0);
  const agg={};rows.forEach(r=>{const k=(r.en||r.ar).trim().toLowerCase();agg[k]=agg[k]||{n:ar?(r.ar||r.en):(r.en||r.ar),v:0};agg[k].v+=n2(r.amt)});
  const payees=Object.values(agg);const top=[...payees].sort((a,b)=>b.v-a.v).slice(0,5);const topSum=top.reduce((s,x)=>s+x.v,0);
  const largest=rows.reduce((m,r)=>n2(r.amt)>n2(m?.amt)?r:m,null);
  const sal=rows.filter(r=>r.type==="w").reduce((s,r)=>s+n2(r.amt),0),sup=total-sal;
  const salNames=rows.filter(r=>r.type==="w").map(r=>ar?(r.ar||r.en):(r.en||r.ar).replace(/\s*salary$/i,""));
  const st={pending:[L("PENDING","قيد الانتظار"),""],paid:[L("PAID","مدفوع"),"paid"],partial:[L("PARTIAL","مدفوع جزئيًا"),"part"]};
  const statuses=[...new Set(rows.map(r=>r.status))];
  const pct=v=>total?(v/total*100):0, pctS=v=>{const x=pct(v);return x>0&&x<0.1?"<0.1%":x.toFixed(1)+"%"};
  const B=[];
  B.push({html:`<div class="fr-top"><span class="eyebrow">${L("Financial Report","تقرير مالي")}</span><span>${L("Report date","تاريخ التقرير")}: <b>${fmtDate(d.date,d.lang)}</b> · ${L("Currency","العملة")}: <b>${ar?C.ar:C.code}</b></span></div>
   <div class="fr-title">${esc(L(d.titleEn,d.titleAr))}</div><div class="fr-sub">${esc(L(d.subEn,d.subAr))}</div>`});
  B.push({html:`<div class="kpis">
   <div class="kpi dark"><div class="k">${L("Total Outstanding","إجمالي المستحقات")}</div><div class="v num">${money(total)}<small>${cc}</small></div><div class="s">${statuses.length===1&&statuses[0]==="pending"?L("All items pending settlement","جميع البنود بانتظار السداد"):L("All listed items","جميع البنود المدرجة")}</div></div>
   <div class="kpi"><div class="k">${L("Line Items","عدد البنود")}</div><div class="v num">${rows.length}</div><div class="s">${payees.length} ${L("payees","جهة مستفيدة")}</div></div>
   <div class="kpi"><div class="k">${L("Largest Item","أكبر بند")}</div><div class="v num">${largest?money(largest.amt):"—"}</div><div class="s">${largest?esc(ar?(largest.ar||largest.en):largest.en):""}</div></div></div>`});
  const mx=top[0]?.v||1;
  B.push({html:`<div class="duo">
   <div class="box"><h4>${L("Top 5 payees","أعلى 5 جهات مستفيدة")}</h4><div class="bars">${top.map(x=>`<span class="n">${esc(x.n)}</span><span class="t"><i style="width:${(x.v/mx*100).toFixed(1)}%"></i></span><span class="a num">${money(x.v)}</span>`).join("")}</div>
   <div class="cap">${L(`Top 5 account for ${money(topSum)} ${C.code} (${pct(topSum).toFixed(1)}% of the total).`,`تمثل أعلى 5 جهات ${money(topSum)} ${C.arCode} (${pct(topSum).toFixed(1)}% من الإجمالي).`)}</div></div>
   <div class="box"><h4>${L("Composition","توزيع المستحقات")}</h4><div class="comp"><i style="width:${total?(sup/total*100).toFixed(2):100}%"></i></div>
   <div class="leg"><span>${L("Suppliers & expenses","الموردون والمصروفات")}</span><b class="num">${money(sup)}</b></div>
   <div class="leg g"><span>${L("Salaries","الرواتب")}</span><b class="num">${money(sal)}</b></div>
   <div class="cap">${salNames.length?L(`Salaries = items marked as salary (${esc(salNames.join(", "))}): ${pct(sal).toFixed(1)}% of total.`,`الرواتب = البنود المصنفة كرواتب (${esc(salNames.join("، "))}): ${pct(sal).toFixed(1)}% من الإجمالي.`):L("No salary items in this statement.","لا توجد رواتب في هذا الكشف.")} ${L(`Status of all ${rows.length} items:`,`حالة جميع البنود الـ${rows.length}:`)} <b>${statuses.map(s=>st[s][0]).join(" / ")}</b>.</div></div></div>`});
  const head=`<thead><tr><th style="width:9mm">#</th><th>${L("Supplier / Payee","المورد / الجهة المستفيدة")}</th><th class="c" style="width:14mm">${L("Qty","الكمية")}</th><th class="r" style="width:30mm">${L(`Amount (${C.code})`,`المبلغ (${C.arCode})`)}</th><th class="r" style="width:16mm">${L("Share","النسبة")}</th><th class="c" style="width:26mm">${L("Status","الحالة")}</th></tr></thead>`;
  rows.forEach((r,i)=>{const nm=ar?`${esc(r.ar||r.en)}${r.ar&&r.en&&r.ar!==r.en?`<small>${esc(r.en)}</small>`:""}`:esc(r.en||r.ar);
    B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td class="nm">${nm}</td><td class="c num">${int(r.qty)}</td><td class="r num">${money(r.amt)}</td><td class="r sh num">${pctS(r.amt)}</td><td class="c"><span class="pill ${st[r.status][1]}">${st[r.status][0]}</span></td></tr>`,table:"fin",tcls:"ft",head})});
  B.push({html:`<div class="gt"><span>${L("Grand Total","الإجمالي العام")}</span><b class="num">${money(total)} ${cc}</b></div>`});
  B.push({html:`<div class="sigs"><div class="sig">${L("Prepared by","أعدّه")}${d.prepared?`<b>${esc(d.prepared)}</b>`:""}</div><div class="sig">${L("Approved by","اعتمده")}${d.approved?`<b>${esc(d.approved)}</b>`:""}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};

const DL={en:{t2:"Daily Horse Care & Dietary Log",horse:"Horse Name",dob:"Date of Birth",sire:"Sire",dam:"Dam",preg:"Pregnancy Status",box:"Stable / Box No.",working:"Working / In Training",work:"Work Details",yes:"YES",no:"NO",item:"Diet / Feed Item",notes:"Notes / Instructions",date:"Date",rec:"Recorded by",chk:"Checked by"},
 ar:{t2:"سجل الرعاية والتغذية اليومي للخيل",horse:"اسم الخيل",dob:"تاريخ الميلاد",sire:"الأب",dam:"الأم",preg:"حالة الحمل",box:"الإسطبل / رقم البوكس",working:"في العمل / التدريب",work:"تفاصيل العمل",yes:"نعم",no:"لا",item:"العلف / المكمل",notes:"ملاحظات / تعليمات",date:"التاريخ",rec:"سجّله",chk:"راجعه"}};
R.diet=d=>{
  const T=DL[d.lang]||DL.en,ar=d.lang==="ar",B=[];
  if(d.date)B.push({html:`<div class="dl-date">${T.date}: <b>${fmtDate(d.date,d.lang)}</b></div>`});
  const radio=`<span class="radio"><span><i class="${d.working==="yes"?"on":""}"></i>${T.yes}</span><span><i class="${d.working==="no"?"on":""}"></i>${T.no}</span></span>`;
  B.push({html:`<table class="it" style="width:100%"><tr><td class="l">${T.horse}</td><td class="v">${esc(d.horse)}</td><td class="l">${T.dob}</td><td class="v">${esc(d.dob)}</td></tr>
   <tr><td class="l">${T.sire}</td><td class="v">${esc(d.sire)}</td><td class="l">${T.dam}</td><td class="v">${esc(d.dam)}</td></tr>
   <tr><td class="l">${T.preg}</td><td class="v">${esc(AR(d,"preg",ar))}</td><td class="l">${T.box}</td><td class="v">${esc(d.box)}</td></tr>
   <tr><td class="l">${T.working}</td><td class="v">${radio}</td><td class="l">${T.work}</td><td class="v">${esc(AR(d,"work",ar))}</td></tr></table>`});
  const head=`<thead><tr><th>${T.item}</th>${d.slots.map(s=>`<th>${esc(ar?s.ar:s.en)}</th>`).join("")}</tr></thead>`;
  d.items.forEach(it=>B.push({row:`<tr><td class="l">${esc(it.name)}</td>${[0,1,2,3,4].map(i=>`<td>${esc(it["s"+i])}</td>`).join("")}</tr>`,table:"diet",tcls:"dt",head}));
  B.push({row:`<tr><td class="l">${T.notes}</td><td class="notes" colspan="5">${esc(AR(d,"notes",ar))}</td></tr>`,table:"diet",tcls:"dt",head});
  B.push({html:`<div class="sigs" style="padding-top:8mm"><div class="sig">${T.rec}${d.recorded?`<b>${esc(d.recorded)}</b>`:""}</div><div class="sig">${T.chk}${d.checked?`<b>${esc(d.checked)}</b>`:""}</div></div>`});
  return{dir:ar?"rtl":"ltr",noFoot:true,blocks:B};
};

R.po=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,C=CUR[d.currency]||CUR.QAR,cc=ar?C.arCode:C.code,B=[];
  const items=d.items.filter(i=>i.d||n2(i.q)||n2(i.p));
  const sub=items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0),disc=n2(d.discount),tot=sub-disc;
  B.push({html:`<div class="po-h"><div><div class="eyebrow">${L("SK Arabian for Trading","اس كي ارابيان للتجارة")}</div><h1>${L("PURCHASE ORDER","أمر شراء")}</h1></div>
   <div class="po-meta"><span>${L("PO No.","رقم الأمر")}</span><b class="num">${esc(d.no)}</b><span>${L("Date","التاريخ")}</span><b>${fmtDate(d.date,d.lang)}</b>${d.ref?`<span>${L("Reference","المرجع")}</span><b>${esc(d.ref)}</b>`:""}</div></div>`});
  const dl=(pairs)=>`<dl>${pairs.filter(p=>p[1]).map(p=>`<dt>${p[0]}</dt><dd>${esc(p[1])}</dd>`).join("")}</dl>`;
  B.push({html:`<div class="parties"><div class="party"><h4>${L("Supplier","المورد")}</h4><div class="nm">${esc(AR(d,"supplier",ar))}</div>${dl([[L("Contact","المسؤول"),d.contact],[L("Phone","الهاتف"),d.phone],[L("Email","البريد"),d.email]])}</div>
   <div class="party"><h4>${L("Deliver to","التسليم إلى")}</h4><div class="nm">${esc(AR(d,"shipTo",ar))}</div>${dl([[L("Delivery date","تاريخ التسليم"),d.delivery?fmtDate(d.delivery,d.lang):""],[L("Payment","الدفع"),AR(d,"payment",ar)],[L("Currency","العملة"),ar?C.ar:C.code]])}</div></div>`});
  const head=`<thead><tr><th style="width:9mm">#</th><th>${L("Description","الوصف")}</th><th class="c" style="width:16mm">${L("Qty","الكمية")}</th><th class="c" style="width:18mm">${L("Unit","الوحدة")}</th><th class="r" style="width:26mm">${L("Unit price","سعر الوحدة")}</th><th class="r" style="width:30mm">${L("Total","الإجمالي")}</th></tr></thead>`;
  items.forEach((it,i)=>B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td>${esc(AR(it,"d",ar))}</td><td class="c num">${int(it.q)}</td><td class="c">${esc(it.u)}</td><td class="r num">${money(it.p)}</td><td class="r num">${money(n2(it.q)*n2(it.p))}</td></tr>`,table:"po",tcls:"ft",head}));
  B.push({html:`<div class="totals"><div class="words"><span>${L("Amount in words","المبلغ كتابةً")}</span>${esc(ar?wordsAr(tot,d.currency):wordsEn(tot,d.currency))}</div>
   <table class="tt"><tr><td>${L("Subtotal","المجموع")}</td><td class="num">${money(sub)}</td></tr>${disc?`<tr><td>${L("Discount","الخصم")}</td><td class="num">− ${money(disc)}</td></tr>`:""}<tr class="grand"><td>${L("Total","الإجمالي")} (${cc})</td><td class="num">${money(tot)}</td></tr></table></div>`});
  const tx=ar?(d.termsAr||""):(d.terms||"");if(tx.trim())B.push({html:`<div class="terms"><h4>${L("Terms & notes","الشروط والملاحظات")}</h4><div>${esc(tx)}</div></div>`});
  B.push({html:`<div class="sigs three"><div class="sig">${L("Requested by","طلب بواسطة")}${d.requested?`<b>${esc(d.requested)}</b>`:""}</div><div class="sig">${L("Approved by","اعتمده")}${d.approved?`<b>${esc(d.approved)}</b>`:""}</div><div class="sig">${L("Supplier acceptance & stamp","موافقة المورد والختم")}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};

R.sal=d=>{
  const m=d.gender!=="f",amt=int(d.salary);
  const en=`<p><b>To Whom It May Concern</b>${d.toEn?`<br>${esc(d.toEn)}`:""}${d.cityEn?`<br>${esc(d.cityEn)}`:""}</p>
   <p>This is to certify that <b>${m?"Mr.":"Ms."} ${esc(d.nameEn)}</b>, ${esc(d.natEn)} national, holder of Qatar ID No. <b>${esc(d.qid)}</b>${d.qidExp?` (valid until <b>${esc(d.qidExp)}</b>)`:""}, is employed with <b>SK Arabian Trading</b> in the position of <b>${esc(d.posEn)}</b> since <b>${esc(d.sinceEn)}</b>.</p>
   <p>${m?"He":"She"} receives a total monthly salary of <b>QAR ${amt}/-</b> (${esc(wordsEn(d.salary,"QAR"))}).</p>
   <p>This certificate has been issued at ${m?"his":"her"} request${d.toEn?` for submission to ${esc(d.toEn)}`:""}, without any liability on the part of the company.</p>`;
  const arT=`<p><b>إلى من يهمه الأمر</b>${d.toAr?`<br>السادة / ${esc(d.toAr)} المحترمين`:""}${d.cityAr?`<br>${esc(d.cityAr)}`:""}</p>
   <p>تشهد <b>اس كي ارابيان للتجارة</b> بأن ${m?"السيد":"السيدة"} / <b>${esc(d.nameAr)}</b>، ${esc(d.natAr)} الجنسية، ${m?"ويحمل":"وتحمل"} البطاقة الشخصية القطرية رقم <b class="num">${esc(d.qid)}</b>${d.qidExp?` (صالحة حتى <b class="num">${esc(d.qidExp)}</b>)`:""}، ${m?"يعمل":"تعمل"} لدينا بوظيفة <b>${esc(d.posAr)}</b> اعتباراً من <b>${esc(d.sinceAr)}</b>.</p>
   <p>${m?"ويتقاضى":"وتتقاضى"} راتباً شهرياً إجمالياً قدره <b><span class="num">${amt}</span> ريال قطري</b> (${esc(wordsAr(d.salary,"QAR"))}).</p>
   <p>وقد أُعطيت ${m?"له":"لها"} هذه الشهادة بناءً على ${m?"طلبه":"طلبها"}${d.toAr?` لتقديمها إلى ${esc(d.toAr)}`:""}، دون أدنى مسؤولية على الشركة.</p>`;
  return{dir:"ltr",blocks:[{html:`<div class="sc-date"><span>Date: ${fmtDate(d.date,"en")}</span><span dir="rtl" style="font-family:'IBM Plex Sans Arabic',sans-serif">التاريخ: ${fmtDate(d.date,"ar")}</span></div>
   <div class="sc-title"><div class="en">SALARY CERTIFICATE</div><div class="ar">شهادة راتب</div><hr></div>
   <div class="sc-cols"><div class="sc-col">${en}</div><div class="rule"></div><div class="sc-col ar">${arT}</div></div>
   <div class="sc-sign"><b>${esc(d.signName)}</b><div>Authorized Signatory &nbsp;|&nbsp; <span style="font-family:'IBM Plex Sans Arabic',sans-serif">المفوض بالتوقيع</span></div></div>`}]};
};

const ORD=["الأول","الثاني","الثالث","الرابع","الخامس","السادس","السابع","الثامن","التاسع","العاشر","الحادي عشر","الثاني عشر","الثالث عشر","الرابع عشر","الخامس عشر"];
function offerTokens(d,ar){const C=CUR[d.salCur]||CUR.QAR;return{
  name:ar?d.nameAr:d.nameEn,hon:ar?d.honAr:d.honEn,position:ar?d.posAr:d.posEn,owner:ar?d.ownerShortAr:d.ownerShortEn,
  salary:ar?`${int(d.salAmt)} ${C.ar}`:`${C.code} ${int(d.salAmt)}`,
  salaryWords:ar?wordsAr(d.salAmt,d.salCur,false):wordsEn(d.salAmt,d.salCur,false),
  salaryQar:ar?`${int(d.salQar)} ريالًا قطريًا`:`QAR ${int(d.salQar)}`,
  salaryQarShort:ar?`${int(d.salQar)} ريال قطري`:`QAR ${int(d.salQar)}`}}
function fill(s,t){return String(s||"").replace(/\{(\w+)\}/g,(m,k)=>k in t?t[k]:m)}
function para(s){const lines=String(s).split("\n");let h="",ol=false;for(const ln of lines){const m=/^\s*\d+[.)]\s+(.*)$/.exec(ln);if(m){if(!ol){h+="<ol>";ol=true}h+=`<li>${rich(m[1])}</li>`}else{if(ol){h+="</ol>";ol=false}if(ln.trim())h+=`<p>${rich(ln)}</p>`}}if(ol)h+="</ol>";return h}
R.offer=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,t=offerTokens(d,ar),F=s=>fill(s,t),f=d.gender==="f",B=[];
  B.push({html:`<div class="of-ref"><span>${L("Ref","الرقم المرجعي")}: <b class="num">${esc(d.ref)}</b></span><span>${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang,true)}${ar?"م":""}</b></span></div>`});
  B.push({html:`<div class="of-title"><h1>${L("Official Offer of Employment","عرض عمل رسمي")}</h1><div class="sub">${esc(L("Position of "+d.posEn,"لشغل منصب "+d.posAr))}</div><div class="dia">◆◆◆</div></div>`});
  B.push({html:`<table class="ci" style="width:100%"><tr><td class="l">${L("Candidate",f?"المرشحة":"المرشح")}</td><td class="v">${esc(ar?`${d.honAr} / ${d.nameAr}`:`${d.honEn} ${d.nameEn}`)}</td><td class="l">${L("Nationality","الجنسية")}</td><td class="v">${esc(ar?d.natAr:d.natEn)}</td></tr>
   <tr><td class="l">${L("Passport No.","رقم جواز السفر")}</td><td class="v num">${esc(d.passport)}</td><td class="l">${L("Position","المنصب")}</td><td class="v">${esc(ar?d.posAr:d.posEn)}</td></tr></table>`});
  B.push({html:`<p class="of-p">${rich(F(ar?d.greetAr:d.greetEn))}</p>`});
  B.push({html:`<div class="of-p">${para(F(ar?d.introAr:d.introEn))}</div>`});
  const mtable=()=>`<table class="mt" style="width:100%"><thead><tr><th style="width:36%">${L("Element","البند المالي")}</th><th style="width:30%">${L("Amount","القيمة")}</th><th>${L("Payment Terms","تفاصيل الصرف")}</th></tr></thead><tbody>${d.money.map(m=>{const am=F(ar?m.amAr:m.amEn).split("\n");return `<tr><td>${rich(F(ar?m.elAr:m.elEn))}</td><td>${rich(am[0])}${am[1]?`<small>${rich(am.slice(1).join(" "))}</small>`:""}</td><td>${rich(F(ar?m.ptAr:m.ptEn))}</td></tr>`}).join("")}</tbody></table>`;
  d.clauses.forEach((c,i)=>{
    B.push({html:`<div class="cl-h"><small>${L("Clause "+(i+1),"البند "+(ORD[i]||i+1))}</small><b>${esc(F(ar?c.tAr:c.tEn))}</b></div><div class="cl-b">${para(F(ar?c.bAr:c.bEn))}</div>`});
    if(+d.tableAfter===i+1&&d.money.length)B.push({html:mtable()});
  });
  if(d.money.length&&(+d.tableAfter<1||+d.tableAfter>d.clauses.length))B.push({html:mtable()});
  B.push({html:`<div class="of-wit">${L("In witness whereof, the parties have signed","وإثباتًا لما تقدّم، وقّع الطرفان")}</div>
   <div class="of-sig" style="margin-top:4mm"><div><h4>${L("For and on behalf of the Employer","عن صاحب العمل وبالنيابة عنه")}</h4>
   <dl><dt>${L("Entity:","المنشأة:")}</dt><dd>${L("SK Arabian for Trading","اس كي ارابيان للتجارة")}</dd><dt>${L("Name:","الاسم:")}</dt><dd>${esc(ar?d.ownerAr:d.ownerEn)}</dd><dt>${L("QID No.:","الرقم الشخصي:")}</dt><dd class="num">${esc(d.ownerQid)}</dd><dt>${L("Title:","الصفة:")}</dt><dd>${esc(ar?d.ownerTitleAr:d.ownerTitleEn)}</dd></dl>
   <div class="line">${L("Signature & Stamp","التوقيع والختم")}</div><div class="dt2">${L("Date:","التاريخ:")} <b class="num">${slashDate(d.date)}</b></div></div>
   <div><h4>${L("Acceptance by the Candidate",f?"إقرار وقبول المرشحة":"إقرار وقبول المرشح")}</h4>
   <p class="decl">${L("I, the undersigned, hereby confirm that I have read and understood all the terms and conditions set out in this offer, and I accept them in full.",f?"أُقرّ أنا الموقّعة أدناه بأنني قد اطّلعت على جميع الشروط والبنود الواردة في عرض العمل هذا وفهمتها، وأعلن قبولي التام لها.":"أُقرّ أنا الموقّع أدناه بأنني قد اطّلعت على جميع الشروط والبنود الواردة في عرض العمل هذا وفهمتها، وأعلن قبولي التام لها.")}</p>
   <dl><dt>${L("Name:","الاسم:")}</dt><dd>${esc(ar?`${d.honAr} / ${d.nameAr}`:`${d.honEn} ${d.nameEn}`)}</dd><dt>${L("Passport No.:","رقم الجواز:")}</dt><dd class="num">${esc(d.passport)}</dd></dl>
   <div class="line">${L("Signature","التوقيع")}</div><div class="dt2">${L("Date:","التاريخ:")} <span class="num">____ / ____ / ________</span></div></div></div>`});
  const pos=ar?d.posAr:d.posEn;
  return{dir:ar?"rtl":"ltr",serif:true,note:esc(L(`SK Arabian for Trading — Official Offer of Employment — ${pos} — Private & Confidential`,`اس كي ارابيان للتجارة — عرض عمل رسمي — ${pos} — خاص وسري`)),blocks:B};
};

const SEX={mare:["Mare","فرس"],stallion:["Stallion","حصان (فحل)"],gelding:["Gelding","حصان مخصي"],filly:["Filly","مهرة"],colt:["Colt","مهر"]};
R.transfer=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,C=CUR[d.currency]||CUR.QAR,B=[];
  const td=fmtDate(d.transferDate,d.lang)||"";
  B.push({html:`<div class="tr-ref"><span>${L("Certificate No.","رقم الشهادة")}: <b class="num">${esc(d.no)}</b></span><span>${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang)}</b></span></div>`});
  B.push({html:`<div class="tr-title"><h1>${L("Certificate of Transfer of Ownership","شهادة نقل ملكية خيل")}</h1><div class="sub">${L("Arabian Horse","خيل عربي")}</div></div>`});
  const sx=SEX[d.sex]||["",""];
  B.push({html:`<div class="sech">${L("Horse details","بيانات الخيل")}</div><table class="kv">
   <tr><td class="l">${L("Horse name","اسم الخيل")}</td><td class="v">${esc(d.hName)}</td><td class="l">${L("Breed","السلالة")}</td><td class="v">${esc(ar?d.breedAr:d.breed)}</td></tr>
   <tr><td class="l">${L("Sex","الجنس")}</td><td class="v">${ar?sx[1]:sx[0]}</td><td class="l">${L("Colour","اللون")}</td><td class="v">${esc(ar?d.colourAr:d.colourEn)}</td></tr>
   <tr><td class="l">${L("Date of birth","تاريخ الميلاد")}</td><td class="v">${esc(d.dob)}</td><td class="l">${L("Microchip No.","رقم الشريحة الإلكترونية")}</td><td class="v num">${esc(d.chip)}</td></tr>
   <tr><td class="l">${L("Sire","الأب")}</td><td class="v">${esc(d.sire)}</td><td class="l">${L("Dam","الأم")}</td><td class="v">${esc(d.dam)}</td></tr>
   <tr><td class="l">${L("Passport / Reg. No.","رقم الجواز / التسجيل")}</td><td class="v num">${esc(d.passport)}</td><td class="l">${L("Country of birth","بلد الولادة")}</td><td class="v">${esc(d.origin)}</td></tr></table>`});
  const party=(t,n,id,nat,ph)=>`<div class="party"><h4>${t}</h4><div class="nm">${esc(n)}</div><dl>${[[L("ID / C.R. No.","رقم الهوية / السجل"),id],[L("Nationality","الجنسية"),nat],[L("Phone","الهاتف"),ph]].filter(x=>x[1]).map(x=>`<dt>${x[0]}</dt><dd class="num">${esc(x[1])}</dd>`).join("")}</dl></div>`;
  B.push({html:`<div class="parties">${party(L("Seller (previous owner)","البائع (المالك السابق)"),ar?d.sNameAr:d.sNameEn,d.sId,ar?(d.sNatAr||d.sNat):d.sNat,d.sPhone)}${party(L("Buyer (new owner)","المشتري (المالك الجديد)"),ar?d.bNameAr:d.bNameEn,d.bId,ar?(d.bNatAr||d.bNat):d.bNat,d.bPhone)}</div>`});
  if(d.showPrice==="yes"&&n2(d.price)){const ps={paid:L("Paid in full","مدفوع بالكامل"),partial:L("Partly paid","مدفوع جزئيًا"),pending:L("Not yet paid","غير مدفوع")}[d.payStatus]||"";
    B.push({html:`<div class="sech">${L("Sale details","بيانات البيع")}</div><table class="kv"><tr><td class="l">${L("Transfer date","تاريخ النقل")}</td><td class="v">${td}</td><td class="l">${L("Sale price","سعر البيع")}</td><td class="v num">${money(d.price)} ${ar?C.arCode:C.code}</td></tr>
     <tr><td class="l">${L("Amount in words","المبلغ كتابةً")}</td><td class="v" colspan="1">${esc(ar?wordsAr(d.price,d.currency):wordsEn(d.price,d.currency))}</td><td class="l">${L("Payment","الدفع")}</td><td class="v">${ps}</td></tr></table>`})}
  else B.push({html:`<table class="kv"><tr><td class="l">${L("Transfer date","تاريخ النقل")}</td><td class="v" colspan="3">${td}</td></tr></table>`});
  B.push({html:`<div class="sech">${L("Declaration","الإقرار")}</div><div class="decl">${rich(fill(ar?d.declAr:d.declEn,{transferDate:td,horse:esc(d.hName)}))}</div>`});
  B.push({html:`<div class="sigs three" style="padding-top:16mm"><div class="sig">${L("Seller's signature","توقيع البائع")}<b>${esc(ar?d.sNameAr:d.sNameEn)}</b></div><div class="sig">${L("Buyer's signature","توقيع المشتري")}<b>${esc(ar?d.bNameAr:d.bNameEn)}</b></div><div class="sig">${L("Witness / stud stamp","الشاهد / ختم المربط")}${d.witness?`<b>${esc(d.witness)}</b>`:""}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};
R.inv=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,C=CUR[d.currency]||CUR.QAR,cc=ar?C.arCode:C.code,B=[];
  if(d.mode==="receipt"){
    const amt=n2(d.rAmount);const M={cash:L("Cash","نقدًا"),cheque:L("Cheque","شيك"),transfer:L("Bank transfer","تحويل بنكي"),card:L("Card","بطاقة")};
    B.push({html:`<div class="po-h"><div><div class="eyebrow">${L("SK Arabian for Trading","اس كي ارابيان للتجارة")}</div><h1>${L("RECEIPT VOUCHER","سند قبض")}</h1></div><div class="po-meta"><span>${L("Receipt No.","رقم السند")}</span><b class="num">${esc(d.rno)}</b><span>${L("Date","التاريخ")}</span><b>${fmtDate(d.date,d.lang)}</b></div></div>`});
    B.push({html:`<div class="rc-amt"><span>${L("Amount","المبلغ")}</span><b class="num">${money(amt)} ${cc}</b></div>`});
    B.push({html:`<div class="rc-lines">
     <div class="rc-line"><span>${L("Received from","استلمنا من")}</span><div>${esc(d.cName)}</div></div>
     <div class="rc-line"><span>${L("The sum of","مبلغ وقدره")}</span><div>${esc(ar?wordsAr(amt,d.currency):wordsEn(amt,d.currency))}</div></div>
     <div class="rc-line"><span>${L("Being payment for","وذلك عن")}</span><div>${esc(AR(d,"rFor",ar))}</div></div>
     <div class="rc-line"><span>${L("Payment method","طريقة الدفع")}</span><div>${M[d.rMethod]||""}${d.rRef?` · ${L("No.","رقم")} <span class="num">${esc(d.rRef)}</span>`:""}${d.rBank?` · ${esc(d.rBank)}`:""}</div></div></div>`});
    B.push({html:`<div class="sigs" style="padding-top:22mm"><div class="sig">${L("Received by","المستلم")}${d.receivedBy?`<b>${esc(d.receivedBy)}</b>`:""}</div><div class="sig">${L("Payer's signature","توقيع الدافع")}</div></div>`});
    return{dir:ar?"rtl":"ltr",blocks:B};
  }
  const items=d.items.filter(i=>i.d||n2(i.p));const sub=items.reduce((s,i)=>s+n2(i.q)*n2(i.p),0),disc=n2(d.discount),tot=sub-disc,paid=n2(d.paid),bal=tot-paid;
  B.push({html:`<div class="po-h"><div><div class="eyebrow">${L("SK Arabian for Trading","اس كي ارابيان للتجارة")}</div><h1>${L("INVOICE","فاتورة")}</h1></div><div class="po-meta"><span>${L("Invoice No.","رقم الفاتورة")}</span><b class="num">${esc(d.no)}</b><span>${L("Date","التاريخ")}</span><b>${fmtDate(d.date,d.lang)}</b>${d.due?`<span>${L("Due date","تاريخ الاستحقاق")}</span><b>${fmtDate(d.due,d.lang)}</b>`:""}</div></div>`});
  B.push({html:`<div class="inv-box"><div class="party"><h4>${L("Bill to","فاتورة إلى")}</h4><div class="nm">${esc(AR(d,"cName",ar))}</div><dl>${[[L("Phone","الهاتف"),d.cPhone],[L("Email","البريد"),d.cEmail],[L("Address","العنوان"),AR(d,"cAddr",ar)]].filter(x=>x[1]).map(x=>`<dt>${x[0]}</dt><dd>${esc(x[1])}</dd>`).join("")}</dl></div>
   <div class="party"><h4>${L("Balance due","المبلغ المستحق")}</h4><div class="nm num" style="font-size:16pt">${money(bal)} ${cc}</div><dl><dt>${L("Currency","العملة")}</dt><dd>${ar?C.ar:C.code}</dd></dl></div></div>`});
  const head=`<thead><tr><th style="width:9mm">#</th><th>${L("Description","الوصف")}</th><th class="c" style="width:16mm">${L("Qty","الكمية")}</th><th class="r" style="width:28mm">${L("Unit price","سعر الوحدة")}</th><th class="r" style="width:30mm">${L("Total","الإجمالي")}</th></tr></thead>`;
  items.forEach((it,i)=>B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td>${esc(AR(it,"d",ar))}</td><td class="c num">${int(it.q)}</td><td class="r num">${money(it.p)}</td><td class="r num">${money(n2(it.q)*n2(it.p))}</td></tr>`,table:"inv",tcls:"ft",head}));
  B.push({html:`<div class="totals"><div class="words"><span>${L("Amount in words","المبلغ كتابةً")}</span>${esc(ar?wordsAr(tot,d.currency):wordsEn(tot,d.currency))}</div>
   <table class="tt"><tr><td>${L("Subtotal","المجموع")}</td><td class="num">${money(sub)}</td></tr>${disc?`<tr><td>${L("Discount","الخصم")}</td><td class="num">− ${money(disc)}</td></tr>`:""}<tr><td><b>${L("Total","الإجمالي")}</b></td><td class="num"><b>${money(tot)}</b></td></tr>${paid?`<tr><td>${L("Paid","المدفوع")}</td><td class="num">− ${money(paid)}</td></tr>`:""}<tr class="grand"><td>${L("Balance due","المبلغ المستحق")} (${cc})</td><td class="num">${money(bal)}</td></tr></table></div>`});
  const pi=ar?d.payInfoAr:d.payInfo;if((pi||"").trim())B.push({html:`<div class="terms"><h4>${L("Payment details","بيانات الدفع")}</h4><div>${esc(pi)}</div></div>`});
  B.push({html:`<div class="sigs" style="padding-top:16mm"><div class="sig">${L("Authorized signature & stamp","التوقيع المعتمد والختم")}</div><div class="sig">${L("Customer acknowledgement","إقرار العميل")}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};
function payNet(r){const e=n2(r.basic)+n2(r.allow)+n2(r.ot),x=n2(r.ded)+n2(r.adv);return{e,x,net:e-x}}
R.pay=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,B=[],mo=fmtMonth(d.month,d.lang),rows=d.staff.filter(r=>(r.name||r.nameAr||"").trim());
  if(d.summary==="yes"&&rows.length){
    const T={b:0,a:0,o:0,d:0,v:0,n:0};rows.forEach(r=>{const p=payNet(r);T.b+=n2(r.basic);T.a+=n2(r.allow);T.o+=n2(r.ot);T.d+=n2(r.ded);T.v+=n2(r.adv);T.n+=p.net});
    B.push({html:`<div class="fr-top"><span class="eyebrow">${L("Payroll","الرواتب")}</span><span>${L("Month","الشهر")}: <b>${mo}</b>${d.payDate?` · ${L("Pay date","تاريخ الصرف")}: <b>${fmtDate(d.payDate,d.lang)}</b>`:""}</span></div><div class="fr-title">${L("Payroll Summary","كشف الرواتب")} — ${mo}</div>`});
    const head=`<thead><tr><th style="width:8mm">#</th><th>${L("Employee","الموظف")}</th><th class="r">${L("Basic","الأساسي")}</th><th class="r">${L("Allowances","البدلات")}</th><th class="r">${L("Overtime","إضافي")}</th><th class="r">${L("Deductions","خصومات")}</th><th class="r">${L("Advance","سلفة")}</th><th class="r">${L("Net pay","الصافي")}</th></tr></thead>`;
    rows.forEach((r,i)=>B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td>${esc(ar?(r.nameAr||r.name):r.name)}<small style="display:block;color:#9aa2b2">${esc(ar?(r.posAr||r.pos):r.pos)}</small></td><td class="r num">${money(r.basic)}</td><td class="r num">${money(r.allow)}</td><td class="r num">${money(r.ot)}</td><td class="r num">${money(r.ded)}</td><td class="r num">${money(r.adv)}</td><td class="r num"><b>${money(payNet(r).net)}</b></td></tr>`,table:"paysum",tcls:"ft",head}));
    B.push({row:`<tr><td></td><td><b>${L("Total","الإجمالي")} (${rows.length})</b></td><td class="r num"><b>${money(T.b)}</b></td><td class="r num"><b>${money(T.a)}</b></td><td class="r num"><b>${money(T.o)}</b></td><td class="r num"><b>${money(T.d)}</b></td><td class="r num"><b>${money(T.v)}</b></td><td class="r num"><b>${money(T.n)}</b></td></tr>`,table:"paysum",tcls:"ft",head});
    B.push({html:`<div class="sigs"><div class="sig">${L("Prepared by","أعدّه")}${d.prepared?`<b>${esc(d.prepared)}</b>`:""}</div><div class="sig">${L("Approved by","اعتمده")}</div></div>`,breakAfter:true});
  }
  rows.forEach(r=>{const p=payNet(r);
    B.push({html:`<div class="slip"><div class="slip-h"><b>${L("Payslip","قسيمة راتب")} — ${mo}</b><span>${L("SK Arabian for Trading","اس كي ارابيان للتجارة")}</span></div>
     <div class="slip-info"><div><span>${L("Employee","الموظف")}</span><b>${esc(ar?(r.nameAr||r.name):r.name)}</b></div><div><span>${L("Position","الوظيفة")}</span>${esc(ar?(r.posAr||r.pos):r.pos)}</div><div><span>${L("Pay date","تاريخ الصرف")}</span>${d.payDate?fmtDate(d.payDate,d.lang):"—"}</div><div><span>${L("Payment method","طريقة الدفع")}</span>${esc(d.method)}</div></div>
     <div class="slip-cols"><table class="sl"><tr><th>${L("Earnings","المستحقات")}</th><th>${L("QAR","ر.ق")}</th></tr><tr><td>${L("Basic salary","الراتب الأساسي")}</td><td class="num">${money(r.basic)}</td></tr><tr><td>${L("Allowances","البدلات")}</td><td class="num">${money(r.allow)}</td></tr><tr><td>${L("Overtime / bonus","عمل إضافي / مكافأة")}</td><td class="num">${money(r.ot)}</td></tr><tr class="t"><td>${L("Total earnings","إجمالي المستحقات")}</td><td class="num">${money(p.e)}</td></tr></table>
     <table class="sl"><tr><th>${L("Deductions","الاستقطاعات")}</th><th>${L("QAR","ر.ق")}</th></tr><tr><td>${L("Deductions","خصومات")}</td><td class="num">${money(r.ded)}</td></tr><tr><td>${L("Salary advance","سلفة")}</td><td class="num">${money(r.adv)}</td></tr><tr><td>&nbsp;</td><td></td></tr><tr class="t"><td>${L("Total deductions","إجمالي الاستقطاعات")}</td><td class="num">${money(p.x)}</td></tr></table></div>
     <div class="net"><div>${L("Net pay","صافي الراتب")}<small>${esc(ar?wordsAr(p.net,"QAR"):wordsEn(p.net,"QAR"))}</small></div><b class="num">${money(p.net)} ${L("QAR","ر.ق")}</b></div>
     <div class="slip-sig"><div>${L("Employee signature","توقيع الموظف")}</div><div>${L("Accountant","المحاسب")}</div></div></div>`});
  });
  if(!rows.length)B.push({html:`<p style="color:#8a93a6">${L("Add staff to print payslips.","أضف الموظفين لطباعة القسائم.")}</p>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};
const VT={vacc:["Vaccination","تطعيم"],worm:["Deworming","مكافحة الديدان"],farr:["Farrier","حدادة"],dent:["Dental","أسنان"],vet:["Vet visit","زيارة بيطرية"],other:["Other","أخرى"]};
function vetStatus(next){if(!next)return null;const t=today();if(next<t)return"over";const d=(new Date(next)-new Date(t))/864e5;return d<=14?"soon":"ok"}
function vetUpcoming(d){const by={};d.entries.forEach(e=>{if(!e.date&&!e.next)return;const k=e.type;if(!by[k]||String(e.date)>String(by[k].date))by[k]=e});return Object.values(by).filter(e=>e.next).sort((a,b)=>String(a.next).localeCompare(String(b.next)))}
R.vet=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,B=[],sx=SEX[d.sex]||["",""];
  const ST={over:L("Overdue","متأخر"),soon:L("Due soon","قريبًا"),ok:L("On schedule","في الموعد")};
  B.push({html:`<div class="tr-title"><h1>${L("Vaccination & Vet Record","سجل التطعيمات والرعاية البيطرية")}</h1><div class="sub">${L("Printed","تاريخ الطباعة")} ${fmtDate(today(),d.lang)}</div></div>`});
  B.push({html:`<table class="kv"><tr><td class="l">${L("Horse name","اسم الخيل")}</td><td class="v">${esc(d.horse)}</td><td class="l">${L("Sex","الجنس")}</td><td class="v">${ar?sx[1]:sx[0]}</td></tr>
   <tr><td class="l">${L("Date of birth","تاريخ الميلاد")}</td><td class="v">${esc(d.dob)}</td><td class="l">${L("Microchip No.","رقم الشريحة")}</td><td class="v num">${esc(d.chip)}</td></tr>
   <tr><td class="l">${L("Sire","الأب")}</td><td class="v">${esc(d.sire)}</td><td class="l">${L("Dam","الأم")}</td><td class="v">${esc(d.dam)}</td></tr>
   <tr><td class="l">${L("Stable / Box No.","الإسطبل / البوكس")}</td><td class="v" colspan="3">${esc(d.box)}</td></tr></table>`});
  const up=vetUpcoming(d);
  if(up.length){const head=`<thead><tr><th>${L("Upcoming","المواعيد القادمة")}</th><th>${L("Last done","آخر مرة")}</th><th>${L("Next due","الموعد القادم")}</th><th class="c">${L("Status","الحالة")}</th></tr></thead>`;
    up.forEach(e=>{const st=vetStatus(e.next);B.push({row:`<tr><td><b>${(VT[e.type]||VT.other)[ar?1:0]}</b><small style="display:block;color:#8a93a6">${esc(AR(e,"what",ar))}</small></td><td>${fmtDate(e.date,d.lang)}</td><td><b>${fmtDate(e.next,d.lang)}</b></td><td class="c">${st?`<span class="vstat ${st}">${ST[st]}</span>`:""}</td></tr>`,table:"vup",tcls:"ft",head})})}
  const hist=[...d.entries].filter(e=>e.date||e.what).sort((a,b)=>String(b.date).localeCompare(String(a.date)));
  const head2=`<thead><tr><th style="width:31mm">${L("Date","التاريخ")}</th><th style="width:30mm">${L("Type","النوع")}</th><th>${L("Details","التفاصيل")}</th><th style="width:26mm">${L("By","بواسطة")}</th><th style="width:26mm">${L("Next due","الموعد القادم")}</th></tr></thead>`;
  if(hist.length)B.push({html:`<div class="sech" style="margin-bottom:0">${L("History","السجل")}</div>`});
  hist.forEach(e=>B.push({row:`<tr><td class="num">${fmtDate(e.date,d.lang)}</td><td>${(VT[e.type]||VT.other)[ar?1:0]}</td><td>${esc(AR(e,"what",ar))}</td><td>${esc(e.by)}</td><td>${fmtDate(e.next,d.lang)}</td></tr>`,table:"vh",tcls:"ft",head:head2}));
  if((d.notes||"").trim())B.push({html:`<div class="terms"><h4>${L("Notes","ملاحظات")}</h4><div>${esc(AR(d,"notes",ar))}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};
const METH={icsi:["ICSI","الحقن المجهري (ICSI)"],natural:["Natural cover","تغطية طبيعية"],fresh:["AI – fresh semen","تلقيح اصطناعي – سائل منوي طازج"],chilled:["AI – chilled semen","تلقيح اصطناعي – سائل منوي مبرّد"],frozen:["AI – frozen semen","تلقيح اصطناعي – سائل منوي مجمّد"],et:["Embryo transfer","نقل أجنة"]};
const PREG={none:["Not checked yet","لم يُفحص بعد"],pos:["Positive (in foal)","إيجابي (عشار)"],neg:["Negative","سلبي"]};
function lastCover(d){return (d.covers||[]).map(c=>c.date).filter(Boolean).sort().pop()||""}
function foalDate(d){const l=lastCover(d);return l?addDays(l,+d.gest||340):""}
R.cover=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,B=[],dates=(d.covers||[]).map(c=>c.date).filter(Boolean).sort();
  B.push({html:`<div class="tr-ref"><span>${L("Certificate No.","رقم الشهادة")}: <b class="num">${esc(d.no)}</b></span><span>${L("Season","الموسم")}: <b class="num">${esc(d.season)}</b> · ${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang)}</b></span></div>`});
  B.push({html:`<div class="tr-title"><h1>${L("Covering Certificate","شهادة التغطية")}</h1><div class="sub">${L("Mare covered by stallion","تغطية فرس بفحل")}</div></div>`});
  B.push({html:`<div class="sech">${L("Mare","الفرس")}</div><table class="kv"><tr><td class="l">${L("Name","الاسم")}</td><td class="v">${esc(d.mare)}</td><td class="l">${L("Date of birth","تاريخ الميلاد")}</td><td class="v">${esc(d.mareDob)}</td></tr>
   <tr><td class="l">${L("Sire","الأب")}</td><td class="v">${esc(d.mareSire)}</td><td class="l">${L("Dam","الأم")}</td><td class="v">${esc(d.mareDam)}</td></tr>
   <tr><td class="l">${L("Passport / Reg. No.","رقم الجواز / التسجيل")}</td><td class="v num">${esc(d.mareReg)}</td><td class="l">${L("Microchip No.","رقم الشريحة")}</td><td class="v num">${esc(d.mareChip)}</td></tr>
   <tr><td class="l">${L("Owner","المالك")}</td><td class="v" colspan="3">${esc(ar?d.ownerAr:d.ownerEn)}${d.ownerPhone?` · <span class="num">${esc(d.ownerPhone)}</span>`:""}</td></tr></table>`});
  B.push({html:`<div class="sech">${L("Stallion","الفحل")}</div><table class="kv"><tr><td class="l">${L("Name","الاسم")}</td><td class="v">${esc(d.stallion)}</td><td class="l">${L("Passport / Reg. No.","رقم الجواز / التسجيل")}</td><td class="v num">${esc(d.stallionReg)}</td></tr>
   <tr><td class="l">${L("Sire","الأب")}</td><td class="v">${esc(d.stallionSire)}</td><td class="l">${L("Dam","الأم")}</td><td class="v">${esc(d.stallionDam)}</td></tr>
   <tr><td class="l">${L("Owner","المالك")}</td><td class="v" colspan="3">${esc(ar?d.stOwnerAr:d.stOwnerEn)}</td></tr></table>`});
  const m=METH[d.method]||METH.natural,pr=PREG[d.checkResult]||PREG.none;
  B.push({html:`<div class="sech">${L("Covering details","بيانات التغطية")}</div><table class="kv"><tr><td class="l">${L("Method","الطريقة")}</td><td class="v">${ar?m[1]:m[0]}</td><td class="l">${L("Covering date(s)","تاريخ / تواريخ التغطية")}</td><td class="v">${dates.map(x=>fmtDate(x,d.lang)).join("<br>")||"—"}</td></tr>
   <tr><td class="l">${L("Pregnancy check","فحص الحمل")}</td><td class="v">${ar?pr[1]:pr[0]}${d.checkDate?`<br><small>${fmtDate(d.checkDate,d.lang)}</small>`:""}</td><td class="l">${L("Veterinarian","الطبيب البيطري")}</td><td class="v">${esc(d.vet)}</td></tr></table>`});
  const fd=foalDate(d);if(fd)B.push({html:`<div class="foal"><span>${L("Expected foaling date","تاريخ الولادة المتوقع")}</span><b>${fmtDate(fd,d.lang)}</b></div><div class="cap" style="margin-top:1.5mm">${L(`Calculated as ${+d.gest||340} days after the last covering date. Actual foaling can vary by a few weeks.`,`محسوب بإضافة ${+d.gest||340} يومًا إلى تاريخ آخر تغطية، وقد يختلف موعد الولادة الفعلي ببضعة أسابيع.`)}</div>`});
  if((d.notes||"").trim())B.push({html:`<div class="terms"><h4>${L("Notes","ملاحظات")}</h4><div>${esc(AR(d,"notes",ar))}</div></div>`});
  B.push({html:`<div class="decl">${L("We certify that the mare named above was covered by the stallion named above on the date(s) shown in this certificate.","نشهد بأن الفرس المذكورة أعلاه قد تمت تغطيتها بالفحل المذكور أعلاه في التاريخ أو التواريخ الموضحة في هذه الشهادة.")}</div>`});
  B.push({html:`<div class="sigs three" style="padding-top:14mm"><div class="sig">${L("Stud manager","مدير المربط")}</div><div class="sig">${L("Veterinarian","الطبيب البيطري")}${d.vet?`<b>${esc(d.vet)}</b>`:""}</div><div class="sig">${L("Stud stamp","ختم المربط")}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};
const INC={feed:["Feed and hay","الأعلاف والتبن"],bedding:["Bedding (shavings)","الفرشة (النشارة)"],turnout:["Daily turnout","التسريح اليومي"],grooming:["Daily grooming","التنظيف والعناية اليومية"],exercise:["Exercise / lunging","التمرين / اللانجينغ"],farrier:["Farrier coordination","تنسيق مواعيد الحدادة"],vet:["Vet coordination","تنسيق الزيارات البيطرية"]};
function boardEnd(d){return d.start?addMonths(d.start,+d.term||12):""}
R.board=d=>{
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e,C=CUR[d.currency]||CUR.QAR,cc=ar?C.arCode:C.code,B=[];
  const horses=(d.horses||[]).filter(h=>(h.name||"").trim()),n=horses.length||1,end=boardEnd(d);
  const t={start:fmtDate(d.start,d.lang),end:fmtDate(end,d.lang),term:esc(d.term),notice:esc(d.notice),dueDay:esc(d.dueDay),fee:ar?`${money(d.fee)} ${C.ar}`:`${C.code} ${money(d.fee)}`,deposit:ar?`${money(d.deposit)} ${C.ar}`:`${C.code} ${money(d.deposit)}`,owner:esc(ar?d.ownerAr:d.ownerEn)};
  B.push({html:`<div class="tr-ref"><span>${L("Ref","المرجع")}: <b class="num">${esc(d.ref)}</b></span><span>${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang)}</b></span></div>`});
  B.push({html:`<div class="tr-title"><h1>${L("Horse Boarding Agreement","اتفاقية إقامة ورعاية خيل")}</h1></div>`});
  B.push({html:`<div class="parties"><div class="party"><h4>${L("The Stud","المربط")}</h4><div class="nm">${L("SK Arabian for Trading","اس كي ارابيان للتجارة")}</div><dl><dt>${L("C.R. No.","السجل التجاري")}</dt><dd class="num">${LH.cr}</dd><dt>${L("Phone","الهاتف")}</dt><dd class="num">${LH.mob}</dd></dl></div>
   <div class="party"><h4>${L("The Owner","المالك")}</h4><div class="nm">${esc(ar?d.ownerAr:d.ownerEn)}</div><dl>${[[L("ID / C.R. No.","رقم الهوية / السجل"),d.ownerId],[L("Phone","الهاتف"),d.ownerPhone]].filter(x=>x[1]).map(x=>`<dt>${x[0]}</dt><dd class="num">${esc(x[1])}</dd>`).join("")}</dl></div></div>`});
  const head=`<thead><tr><th style="width:9mm">#</th><th>${L("Horse","الخيل")}</th><th style="width:40mm">${L("Sex","الجنس")}</th><th style="width:34mm">${L("Box No.","رقم البوكس")}</th></tr></thead>`;
  horses.forEach((h,i)=>{const sx=SEX[h.sex]||["",""];B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td><b>${esc(h.name)}</b></td><td>${ar?sx[1]:sx[0]}</td><td class="num">${esc(h.box)}</td></tr>`,table:"bh",tcls:"ft",head})});
  B.push({html:`<table class="kv"><tr><td class="l">${L("Monthly fee per horse","الرسوم الشهرية للخيل")}</td><td class="v num">${money(d.fee)} ${cc}</td><td class="l">${L("Total per month","الإجمالي الشهري")}</td><td class="v num"><b>${money(n2(d.fee)*n)} ${cc}</b></td></tr>
   <tr><td class="l">${L("Start date","تاريخ البدء")}</td><td class="v">${t.start}</td><td class="l">${L("End date","تاريخ الانتهاء")}</td><td class="v">${t.end}</td></tr>
   <tr><td class="l">${L("Payment due","موعد السداد")}</td><td class="v">${L(`Day ${esc(d.dueDay)} of each month`,`يوم ${esc(d.dueDay)} من كل شهر`)}</td><td class="l">${L("Notice period","مدة الإشعار")}</td><td class="v">${L(`${esc(d.notice)} days`,`${esc(d.notice)} يومًا`)}</td></tr>
   ${n2(d.deposit)?`<tr><td class="l">${L("Deposit","التأمين")}</td><td class="v num" colspan="3">${money(d.deposit)} ${cc}</td></tr>`:""}</table>`});
  B.push({html:`<div class="sech">${L("Included in the monthly fee","يشمل الرسوم الشهرية")}</div><div class="inc">${Object.entries(INC).map(([k,v])=>`<div class="${d.inc&&d.inc[k]?"":"no"}">${ar?v[1]:v[0]}</div>`).join("")}</div>${(ar?d.extrasAr:d.extrasEn)?`<div class="cap">${esc(ar?d.extrasAr:d.extrasEn)}</div>`:""}`});
  const cl=(d.clauses||[]).filter(c=>!(n2(d.deposit)===0&&/\{deposit\}/.test((c.bEn||"")+(c.bAr||""))));
  B.push({html:`<div class="sech">${L("Terms","الشروط")}</div>`});
  cl.forEach((c,i)=>B.push({html:`<div class="ol"><div class="it"><b class="num">${i+1}.</b><div><h5>${esc(ar?c.tAr:c.tEn)}</h5><p>${rich(fill(ar?c.bAr:c.bEn,t))}</p></div></div></div>`}));
  B.push({html:`<div class="sigs" style="padding-top:14mm"><div class="sig">${L("For the Stud – signature & stamp","عن المربط – التوقيع والختم")}</div><div class="sig">${L("The Owner","المالك")}<b>${esc(ar?d.ownerAr:d.ownerEn)}</b></div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B};
};
function eosCalc(d){const a=d.joinDate,b=d.endDate||today();const days=a&&b>=a?dayDiff(a,b)+1:0,years=days/365,elig=days>=365;const daily=n2(d.basic)/30;
  const grat=elig?daily*(+d.dpy||21)*years:0,leave=daily*n2(d.leaveDays);const total=grat+leave+n2(d.unpaid)+n2(d.other)-n2(d.deductions);return{days,years,elig,grat,leave,total,len:ymd(a,addDays(b,1))}}
function lenText(l,ar){const p=[];if(ar){if(l.y)p.push(`${l.y} سنة`);if(l.m)p.push(`${l.m} شهر`);if(l.d||!p.length)p.push(`${l.d} يوم`);return p.join(" و")}if(l.y)p.push(`${l.y} year${l.y>1?"s":""}`);if(l.m)p.push(`${l.m} month${l.m>1?"s":""}`);if(l.d||!p.length)p.push(`${l.d} day${l.d===1?"":"s"}`);return p.join(", ")}
const LEAVE={annual:["Annual leave","إجازة سنوية"],sick:["Sick leave","إجازة مرضية"],emergency:["Emergency leave","إجازة طارئة"],unpaid:["Unpaid leave","إجازة بدون راتب"],other:["Other","أخرى"]};
const EOSR={resignation:["Resignation","استقالة"],end:["End of contract","انتهاء العقد"],termination:["Termination by employer","إنهاء الخدمة من صاحب العمل"],other:["Other","أخرى"]};
R.letters=d=>{
  const m=d.gender!=="f",t=d.type,B=[];
  if(t==="exp"||t==="noc"){
    const jEn=fmtDate(d.joinDate,"en"),jAr=fmtDate(d.joinDate,"ar"),left=!!d.endDate;
    let en,arT;const title=LTYPE[t];
    const head=`<p><b>To Whom It May Concern</b>${d.toEn?`<br>${esc(d.toEn)}`:""}</p>`,headAr=`<p><b>إلى من يهمه الأمر</b>${d.toAr?`<br>السادة / ${esc(d.toAr)} المحترمين`:""}</p>`;
    const who=`<b>${m?"Mr.":"Ms."} ${esc(d.nameEn)}</b>, ${esc(d.natEn)} national, holder of Qatar ID No. <b>${esc(d.qid)}</b>`;
    const whoAr=`${m?"السيد":"السيدة"} / <b>${esc(d.nameAr)}</b>، ${esc(d.natAr)} الجنسية، ${m?"ويحمل":"وتحمل"} البطاقة الشخصية القطرية رقم <b class="num">${esc(d.qid)}</b>`;
    if(t==="exp"){
      en=`${head}<p>This is to certify that ${who}, ${left?"worked":"has been working"} with <b>SK Arabian Trading</b> as <b>${esc(d.posEn)}</b> from <b>${jEn}</b> ${left?`to <b>${fmtDate(d.endDate,"en")}</b>`:"until the date of this certificate"}.</p><p>During ${m?"his":"her"} employment, ${m?"he":"she"} showed dedication, honesty and good conduct.</p><p>This certificate has been issued at ${m?"his":"her"} request without any liability on the part of the company.</p>`;
      arT=`${headAr}<p>تشهد <b>اس كي ارابيان للتجارة</b> بأن ${whoAr}، ${left?(m?"عمل":"عملت"):(m?"يعمل":"تعمل")} لدينا بوظيفة <b>${esc(d.posAr)}</b> اعتبارًا من <b>${jAr}</b> ${left?`وحتى <b>${fmtDate(d.endDate,"ar")}</b>`:"وحتى تاريخه"}.</p><p>وقد ${m?"أبدى":"أبدت"} خلال فترة ${m?"عمله":"عملها"} التزامًا وأمانة وحسن سلوك.</p><p>وقد أُعطيت ${m?"له":"لها"} هذه الشهادة بناءً على ${m?"طلبه":"طلبها"} دون أدنى مسؤولية على الشركة.</p>`;
    }else{
      en=`${head}<p>This is to certify that ${who}, is employed with <b>SK Arabian Trading</b> as <b>${esc(d.posEn)}</b> since <b>${jEn}</b>.</p><p>The company has no objection to ${m?"him":"her"} ${esc(d.purposeEn)}.</p><p>This letter has been issued at ${m?"his":"her"} request without any liability on the part of the company.</p>`;
      arT=`${headAr}<p>تشهد <b>اس كي ارابيان للتجارة</b> بأن ${whoAr}، ${m?"يعمل":"تعمل"} لدينا بوظيفة <b>${esc(d.posAr)}</b> اعتبارًا من <b>${jAr}</b>.</p><p>ولا مانع لدى الشركة من ${esc(d.purposeAr)}.</p><p>وقد أُعطيت ${m?"له":"لها"} هذه الشهادة بناءً على ${m?"طلبه":"طلبها"} دون أدنى مسؤولية على الشركة.</p>`;
    }
    B.push({html:`<div class="sc-date"><span>Ref: <span class="num">${esc(d.ref)}</span> · Date: ${fmtDate(d.date,"en")}</span><span dir="rtl" style="font-family:'IBM Plex Sans Arabic',sans-serif">التاريخ: ${fmtDate(d.date,"ar")}</span></div>
     <div class="sc-title"><div class="en">${title[0].toUpperCase()}</div><div class="ar">${title[1]}</div><hr></div>
     <div class="sc-cols"><div class="sc-col">${en}</div><div class="rule"></div><div class="sc-col ar">${arT}</div></div>
     <div class="sc-sign"><b>${esc(d.signName)}</b><div>Authorized Signatory &nbsp;|&nbsp; <span style="font-family:'IBM Plex Sans Arabic',sans-serif">المفوض بالتوقيع</span></div></div>`});
    return{dir:"ltr",blocks:B};
  }
  const ar=d.lang==="ar",L=(e,a)=>ar?a:e;
  B.push({html:`<div class="tr-ref"><span>${L("Ref","المرجع")}: <b class="num">${esc(d.ref)}</b></span><span>${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang)}</b></span></div><div class="tr-title"><h1>${LTYPE[t][ar?1:0]}</h1></div>`});
  B.push({html:`<div class="sech">${L("Employee","الموظف")}</div><table class="kv"><tr><td class="l">${L("Name","الاسم")}</td><td class="v">${esc(ar?d.nameAr:d.nameEn)}</td><td class="l">${L("Position","الوظيفة")}</td><td class="v">${esc(ar?d.posAr:d.posEn)}</td></tr>
   <tr><td class="l">${L("Qatar ID No.","رقم البطاقة الشخصية")}</td><td class="v num">${esc(d.qid)}</td><td class="l">${L("Joining date","تاريخ الالتحاق")}</td><td class="v">${fmtDate(d.joinDate,d.lang)}</td></tr></table>`});
  if(t==="leave"){
    const days=d.from&&d.to&&d.to>=d.from?dayDiff(d.from,d.to)+1:"",ret=d.to?addDays(d.to,1):"",lt=LEAVE[d.leaveType]||LEAVE.annual;
    B.push({html:`<div class="sech">${L("Leave details","بيانات الإجازة")}</div><table class="kv"><tr><td class="l">${L("Type of leave","نوع الإجازة")}</td><td class="v" colspan="3">${ar?lt[1]:lt[0]}</td></tr>
     <tr><td class="l">${L("From","من")}</td><td class="v">${fmtDate(d.from,d.lang)}</td><td class="l">${L("To","إلى")}</td><td class="v">${fmtDate(d.to,d.lang)}</td></tr>
     <tr><td class="l">${L("Number of days","عدد الأيام")}</td><td class="v num">${days}</td><td class="l">${L("Back to work on","تاريخ العودة للعمل")}</td><td class="v">${fmtDate(ret,d.lang)}</td></tr>
     <tr><td class="l">${L("Reason","السبب")}</td><td class="v" colspan="3">${esc(AR(d,"reason",ar))}</td></tr>
     <tr><td class="l">${L("Replacement during leave","البديل أثناء الإجازة")}</td><td class="v">${esc(d.replacement)}</td><td class="l">${L("Contact during leave","رقم التواصل أثناء الإجازة")}</td><td class="v num">${esc(d.contact)}</td></tr></table>`});
    B.push({html:`<div class="sigs" style="padding-top:12mm"><div class="sig">${L("Employee signature","توقيع الموظف")}<b>${esc(ar?d.nameAr:d.nameEn)}</b></div><div class="sig">${L("Date","التاريخ")}</div></div>`});
    B.push({html:`<div class="sech" style="margin-top:4mm">${L("For office use","خاص بالإدارة")}</div><div class="chkl"><span><i class="${d.decision==="approved"?"on":""}"></i>${L("Approved","موافق")}</span><span><i class="${d.decision==="rejected"?"on":""}"></i>${L("Not approved","غير موافق")}</span></div><div class="sigs"><div class="sig">${L("Direct manager","المدير المباشر")}</div><div class="sig">${L("Management","الإدارة")}</div></div>`});
  }else{
    const c=eosCalc(d),C=CUR.QAR,cc=ar?C.arCode:C.code,r=EOSR[d.eosReason]||EOSR.other;
    B.push({html:`<div class="sech">${L("Service","الخدمة")}</div><table class="kv"><tr><td class="l">${L("Last working day","آخر يوم عمل")}</td><td class="v">${fmtDate(d.endDate,d.lang)||"—"}</td><td class="l">${L("Length of service","مدة الخدمة")}</td><td class="v">${lenText(c.len,ar)}</td></tr>
     <tr><td class="l">${L("Reason","سبب انتهاء الخدمة")}</td><td class="v">${ar?r[1]:r[0]}</td><td class="l">${L("Basic monthly salary","الراتب الأساسي الشهري")}</td><td class="v num">${money(d.basic)} ${cc}</td></tr></table>`});
    const row=(a,b,v,neg)=>`<tr><td>${a}${b?`<small style="display:block;color:#8a93a6">${b}</small>`:""}</td><td class="r num">${neg?"− ":""}${money(v)}</td></tr>`;
    B.push({html:`<table class="ft" style="width:100%"><thead><tr><th>${L("Settlement","التسوية")}</th><th class="r" style="width:40mm">${L("Amount","المبلغ")} (${cc})</th></tr></thead><tbody>
     ${row(L("End-of-service gratuity","مكافأة نهاية الخدمة"),c.elig?L(`${+d.dpy||21} days' basic salary × ${c.years.toFixed(2)} years of service`,`أجر ${+d.dpy||21} يومًا من الراتب الأساسي × ${c.years.toFixed(2)} سنة خدمة`):L("Not due: less than one year of service","غير مستحقة: مدة الخدمة أقل من سنة"),c.grat)}
     ${row(L("Leave balance","رصيد الإجازات"),L(`${n2(d.leaveDays)} days`,`${n2(d.leaveDays)} يومًا`),c.leave)}
     ${row(L("Unpaid salary","رواتب غير مدفوعة"),"",d.unpaid)}${row(L("Other dues","مستحقات أخرى"),"",d.other)}${row(L("Less: deductions & advances","يُخصم: استقطاعات وسلف"),"",d.deductions,true)}
     <tr><td><b>${L("Net amount payable","صافي المبلغ المستحق")}</b></td><td class="r num"><b>${money(c.total)}</b></td></tr></tbody></table>`});
    B.push({html:`<div class="words"><span>${L("Amount in words","المبلغ كتابةً")}</span>${esc(ar?wordsAr(c.total,"QAR"):wordsEn(c.total,"QAR"))}</div><div class="cap">${L(`Gratuity is calculated on the basic salary at ${+d.dpy||21} days per year of service, pro-rata for part years, and is not due for service of less than one year. Check the employment contract for any better terms.`,`تُحسب مكافأة نهاية الخدمة على أساس الراتب الأساسي بواقع ${+d.dpy||21} يومًا عن كل سنة خدمة، وبالتناسب لأجزاء السنة، ولا تُستحق عن خدمة تقل عن سنة. يُرجى مراجعة عقد العمل لأي شروط أفضل.`)}</div>`});
    B.push({html:`<div class="decl">${L("I acknowledge that I have received the above amount in full and final settlement of all my dues from SK Arabian Trading.","أقرّ بأنني استلمت المبلغ المذكور أعلاه كمخالصة نهائية وشاملة لجميع مستحقاتي لدى اس كي ارابيان للتجارة.")}</div>
     <div class="sigs" style="padding-top:12mm"><div class="sig">${L("Employee signature","توقيع الموظف")}<b>${esc(ar?d.nameAr:d.nameEn)}</b></div><div class="sig">${L("For the company – signature & stamp","عن الشركة – التوقيع والختم")}<b>${esc(d.signName)}</b></div></div>`});
  }
  return{dir:ar?"rtl":"ltr",blocks:B};
};
const PLANST={planned:["Planned","مخطط"],booked:["Booked","محجوز"],covered:["Covered","تمت التغطية"],infoal:["In foal","عشار"],notinfoal:["Not in foal","غير عشار"],foaled:["Foaled","وَلدت"],cancelled:["Cancelled","ملغى"]};
const EMBST={frozen:["Frozen","مجمّد"],transferred:["Transferred","منقول"],pregnant:["Recipient in foal","المستقبِلة عشار"],born:["Foal born","وُلد"],lost:["Lost","فُقد"],sold:["Sold","مُباع"]};
const PEDGEN=[["s","d"],["ss","sd","ds","dd"],["sss","ssd","sds","sdd","dss","dsd","dds","ddd"],["ssss","sssd","ssds","ssdd","sdss","sdsd","sdds","sddd","dsss","dssd","dsds","dsdd","ddss","ddsd","ddds","dddd"]];
const AR=(o,k,ar)=>ar&&String(o[k+"Ar"]||"").trim()?o[k+"Ar"]:o[k];
function pedLabel(p,ar){if(ar&&p.length===2){const w=c=>c==="s"?"أب":"أم",a=c=>c==="s"?"الأب":"الأم";return `${w(p[1])} ${a(p[0])}`}const w=ar?{s:"الأب",d:"الأم"}:{s:"Sire",d:"Dam"};if(ar){const parts=[...p].map(c=>c==="s"?"أب":"أم");return parts.length===1?w[p]:parts.reverse().join(" ").replace(/^/,"")}const words=[...p].map(c=>c==="s"?"sire":"dam");return words.length===1?w[p]:words.slice(0,-1).map(x=>x.charAt(0).toUpperCase()+x.slice(1)+"'s").join(" ").replace(/'s (\w)/g,(m,c)=>"'s "+c.toLowerCase())+" "+words[words.length-1]}
function horseAge(dob){const t=Date.parse(dob);if(isNaN(t))return"";const y=(Date.now()-t)/(365.25*864e5);return y>=1?Math.floor(y):""}
R.profile=d=>{const ar=d.lang==="ar",L=(e,a)=>ar?a:e,B=[],sx=SEX[d.sex]||["",""],st=(HSTAT.find(x=>x[0]===d.status)||["",""])[1];
  const STAT_AR={stud:"في المربط",boarding:"إقامة (خيل مالك)",sold:"مُباع",leased:"مؤجَّر",deceased:"نافق"};
  const age=horseAge(d.dob);const nm=ar?(d.nameAr||d.nameEn):(d.nameEn||d.nameAr);let nm2=ar?d.nameEn:d.nameAr;if(low(nm2)===low(nm))nm2="";
  const facts=[[L("Sex","الجنس"),ar?sx[1]:sx[0]],[L("Colour","اللون"),ar?d.colourAr:d.colourEn],[L("Date of birth","تاريخ الميلاد"),d.dob?`${esc(d.dob)}${age!==""?` (${age} ${L(age===1?"year":"years","سنة")})`:""}`:""],[L("Breed","السلالة"),ar?d.breedAr:d.breed],[L("Country of birth","بلد الولادة"),d.origin],[L("Breeder","المربي"),d.breeder],[L("Owner","المالك"),d.owner],[L("Microchip No.","رقم الشريحة"),d.chip],[L("Passport / Reg. No.","رقم الجواز / التسجيل"),d.passport],[L("Height","الارتفاع"),d.height],[L("Status","الحالة"),ar?STAT_AR[d.status]||"":st],[L("Stable / Box","الإسطبل / البوكس"),d.box]].filter(x=>String(x[1]||"").trim());
  B.push({html:`<div class="pf-head"><div class="pf-id"><div class="eyebrow">${L("Horse profile","ملف الخيل")}</div><h1 class="pf-name">${esc(nm||L("Unnamed horse","خيل بدون اسم"))}</h1>${nm2?`<div class="pf-name2">${esc(nm2)}</div>`:""}
   <div class="pf-facts2">${facts.map(([k,v])=>`<div><span>${k}</span><b>${typeof v==="string"&&v.includes("(")&&v===facts[2]?.[1]?v:esc(v)}</b></div>`).join("").replace(/&lt;/g,"<").replace(/&gt;/g,">")}</div></div>
   ${d.photo?`<div class="pf-photo"><img src="${d.photo}" alt=""></div>`:`<div class="pf-photo pf-nophoto">${L("Photo","صورة")}</div>`}</div>`});
  const rows=8;let cells="";PEDGEN.slice(0,3).forEach((g,gi)=>{const span=rows/g.length;g.forEach((p,i)=>{const v=(d["p_"+p]||"").trim();cells+=`<div class="pd-c pd-${p.slice(-1)}" style="grid-column:${gi+1};grid-row:${i*span+1}/span ${span}">${gi<2?`<small>${pedLabel(p,ar)}</small>`:""}<b>${v?esc(v):"—"}</b></div>`})});
  B.push({html:`<div class="sech">${L("Pedigree","النسب")}</div><div class="pd-hdr"><span>${L("Parents","الأبوان")}</span><span>${L("Grandparents","الأجداد")}</span><span>${L("Great-grandparents","آباء الأجداد")}</span></div><div class="pd-grid">${cells}</div>`});
  const shows=(d.shows||[]).filter(x=>x.show||x.result);
  if(shows.length){const head=`<thead><tr><th colspan="4" class="tcap">${L("Show results & achievements","نتائج البطولات والإنجازات")}</th></tr><tr><th style="width:26mm">${L("Date","التاريخ")}</th><th>${L("Show","البطولة")}</th><th>${L("Class","الفئة")}</th><th style="width:34mm">${L("Result","النتيجة")}</th></tr></thead>`;shows.forEach(x=>B.push({row:`<tr><td>${esc(pd(x.date)?fmtDate(x.date,d.lang):x.date)}</td><td>${esc(x.show)}</td><td>${esc(x.cls)}</td><td><b>${esc(x.result)}</b></td></tr>`,table:"pfs",tcls:"ft",head}))}
  const prog=(d.progeny||[]).filter(x=>x.name);
  if(prog.length){const head=`<thead><tr><th colspan="4" class="tcap">${L("Progeny","النتاج")}</th></tr><tr><th>${L("Name","الاسم")}</th><th style="width:20mm">${L("Year","السنة")}</th><th style="width:26mm">${L("Sex","الجنس")}</th><th>${d.sex==="mare"||d.sex==="filly"?L("Sire","الأب"):L("Dam","الأم")}</th></tr></thead>`;prog.forEach(x=>B.push({row:`<tr><td><b>${esc(x.name)}</b></td><td class="num">${esc(x.year)}</td><td>${(SEX[x.sex]||[""])[ar?1:0]}</td><td>${esc(x.other)}</td></tr>`,table:"pfp",tcls:"ft",head}))}
  const male=["stallion","colt","gelding"].includes(d.sex);
  const plan=(d.plan||[]).filter(x=>x.partner||x.date);
  if(plan.length){const head=`<thead><tr><th colspan="6" class="tcap">${L("Breeding program","برنامج التربية")}</th></tr><tr><th style="width:16mm">${L("Season","الموسم")}</th><th>${male?L("Mare","الفرس"):L("Stallion","الفحل")}</th><th style="width:34mm">${L("Method","الطريقة")}</th><th style="width:26mm">${L("Date","التاريخ")}</th><th style="width:26mm">${L("Status","الحالة")}</th><th style="width:28mm">${L("Expected foaling","الولادة المتوقعة")}</th></tr></thead>`;
    plan.forEach(x=>{const ef=x.date&&["covered","infoal"].includes(x.status)&&!male?addDays(x.date,340):"";const pt=PLANST[x.status]||["",""];B.push({row:`<tr><td class="num">${esc(x.season)}</td><td><b>${esc(x.partner)}</b>${x.notes?`<small style="display:block;color:#8a93a6">${esc(x.notes)}</small>`:""}</td><td>${(METH[x.method]||["",""])[ar?1:0]}</td><td>${x.date?fmtDate(x.date,d.lang):""}</td><td>${pt[ar?1:0]}</td><td>${ef?fmtDate(ef,d.lang):""}</td></tr>`,table:"pfpl",tcls:"ft",head})})}
  const covs=(d.covers||[]).filter(c=>lastCover(c));
  if(covs.length){const head=`<thead><tr><th colspan="5" class="tcap">${L("Breeding history (covering certificates)","سجل التغطيات (شهادات التغطية)")}</th></tr><tr><th style="width:24mm">${L("Certificate","الشهادة")}</th><th>${male?L("Mare","الفرس"):L("Stallion","الفحل")}</th><th style="width:30mm">${L("Last covering","آخر تغطية")}</th><th style="width:26mm">${L("Pregnancy","الحمل")}</th><th style="width:28mm">${L("Expected foaling","الولادة المتوقعة")}</th></tr></thead>`;
    covs.sort((a,b)=>String(lastCover(b)).localeCompare(String(lastCover(a)))).forEach(c=>B.push({row:`<tr><td class="num">${esc(c.no)}</td><td><b>${esc(male?c.mare:c.stallion)}</b></td><td>${fmtDate(lastCover(c),d.lang)}</td><td>${(PREG[c.checkResult]||PREG.none)[ar?1:0]}</td><td>${male?"":fmtDate(foalDate(c),d.lang)}</td></tr>`,table:"pfch",tcls:"ft",head}))}
  const emb=(d.embryos||[]).filter(x=>x.date||x.partner||x.stage);
  if(emb.length){const head=`<thead><tr><th colspan="7" class="tcap">${L("Embryos","الأجنّة")}</th></tr><tr><th style="width:27mm">${L("Date","التاريخ")}</th><th>${male?L("Mare","الفرس"):L("Sire","الأب")}</th><th style="width:15mm">${L("Method","الطريقة")}</th><th style="width:28mm">${L("Stage / grade","المرحلة / الدرجة")}</th><th style="width:26mm">${L("Status","الحالة")}</th><th>${L("Recipient mare","الفرس المستقبِلة")}</th><th style="width:20mm">${L("Storage","التخزين")}</th></tr></thead>`;
    const E=Object.keys(EMBST).reduce((o,k)=>(o[k]=0,o),{});emb.forEach(x=>{if(x.status in E)E[x.status]++});
    emb.forEach(x=>B.push({row:`<tr><td>${x.date?fmtDate(x.date,d.lang):""}</td><td><b>${esc(x.partner)}</b></td><td>${x.method==="icsi"?"ICSI":L("ET","نقل أجنة")}</td><td>${esc([x.stage,x.grade?L("Gr. ","درجة ")+x.grade:""].filter(Boolean).join(" · "))}</td><td>${(EMBST[x.status]||["",""])[ar?1:0]}</td><td>${esc(x.recipient)}</td><td>${esc(x.storage)}</td></tr>`,table:"pfem",tcls:"ft",head}));
    B.push({html:`<div class="cap" style="margin-top:-2mm">${L("Summary","الملخص")}: ${Object.entries(E).filter(([,n])=>n).map(([k,n])=>`${(EMBST[k])[ar?1:0]}: <b>${n}</b>`).join(" · ")} · ${L("Total","الإجمالي")}: <b>${emb.length}</b></div>`})}
  const vr=d.vet&&Array.isArray(d.vet.entries)?d.vet:null;
  if(vr){const up=vetUpcoming(vr);if(up.length){const head=`<thead><tr><th colspan="3" class="tcap">${L("Health record","السجل الصحي")}</th></tr><tr><th>${L("Health","الصحة")}</th><th style="width:34mm">${L("Last done","آخر مرة")}</th><th style="width:34mm">${L("Next due","الموعد القادم")}</th></tr></thead>`;up.forEach(e=>B.push({row:`<tr><td>${(VT[e.type]||VT.other)[ar?1:0]}<small style="display:block;color:#8a93a6">${esc(AR(e,"what",ar))}</small></td><td>${fmtDate(e.date,d.lang)}</td><td>${fmtDate(e.next,d.lang)}</td></tr>`,table:"pfh",tcls:"ft",head}))}}
  const nt=ar?d.notesAr:d.notesEn;if((nt||"").trim())B.push({html:`<div class="terms"><h4>${L("About this horse","نبذة عن الخيل")}</h4><div>${esc(nt)}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B,fitOne:true,cls:"pfp"}};
function dobISO(v){v=String(v||"").trim();if(!v)return"";const iso=toISO(v);if(iso)return iso;if(/^\d{4}$/.test(v))return v+"-07-01";const t=Date.parse(v);if(isNaN(t))return"";const d=new Date(t);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,"0")}-${String(d.getDate()).padStart(2,"0")}`}
function ageMonths(iso){if(!iso)return null;const a=iso.split("-").map(Number),t=today().split("-").map(Number);let m=(t[0]-a[0])*12+(t[1]-a[1]);if(t[2]<a[2])m--;return m}
function ageText(m){if(m===null)return"";if(m<1)return"under 1 month";if(m<12)return `${m} month${m>1?"s":""}`;const y=Math.floor(m/12);return `${y} year${y>1?"s":""}`}
R.horses=d=>{const B=[],all=(CTX.horses||[]).map(h=>{const iso=dobISO(h.dob);return{h,iso,m:ageMonths(iso)}});
  const byYoung=(a,b)=>b.iso.localeCompare(a.iso)||String(a.h.name).localeCompare(String(b.h.name));
  const isMale=x=>["stallion","colt","gelding"].includes(String(x.h.sex||"").toLowerCase());const adultsAll=all.filter(x=>x.m!==null&&x.m>=12).sort(byYoung),adults=adultsAll.filter(x=>!isMale(x)),stallions=adultsAll.filter(isMale),foals=all.filter(x=>x.m!==null&&x.m<12).sort(byYoung),unknown=all.filter(x=>x.m===null).sort((a,b)=>String(a.h.name).localeCompare(String(b.h.name)));
  B.push({html:`<div class="fr-top"><span class="eyebrow">Horse register</span><span>As of <b>${fmtDate(today(),"en")}</b></span></div><div class="fr-title">Our Horses</div><div class="fr-sub">${all.length} horse${all.length===1?"":"s"} · ${adults.length} mares one year and older · ${stallions.length} stallion${stallions.length===1?"":"s"} · ${foals.length} under one year${unknown.length?` · ${unknown.length} without a date of birth`:""}</div>`});
  const head=`<thead><tr><th style="width:8mm">#</th><th>Horse</th><th style="width:18mm">Sex</th><th style="width:28mm">Born</th><th>Sire × Dam</th><th style="width:30mm">Microchip</th><th style="width:14mm">Box</th></tr></thead>`;
  let n=0;const grp=(title,list)=>{if(!list.length)return;B.push({row:`<tr class="grp"><td colspan="7">${title} <span>(${list.length})</span></td></tr>`,table:"hl",tcls:"ft",head});
    list.forEach(({h,m})=>{n++;B.push({row:`<tr><td class="idx num">${String(n).padStart(2,"0")}</td><td><b>${esc(h.name)}</b>${h.nameAr?`<small style="display:block;color:#8a93a6">${esc(h.nameAr)}</small>`:""}</td><td>${(SEX[h.sex]||[""])[0]}</td><td>${esc(h.dob||"")}${m!==null?`<small style="display:block;color:#8a93a6">${ageText(m)}</small>`:""}</td><td>${esc([h.sire,h.dam].filter(Boolean).join(" × "))}</td><td class="num">${esc(h.chip||"")}</td><td>${esc(h.box||"")}</td></tr>`,table:"hl",tcls:"ft",head})})};
  grp("Mares · one year and older · youngest first",adults);grp("Stallions · one year and older · youngest first",stallions);grp("Under one year · youngest first",foals);grp("Date of birth not entered",unknown);
  if(!all.length)B.push({html:`<p style="color:#8a93a6">No horses saved yet.</p>`});return{dir:"ltr",blocks:B}};
R.staff=d=>{const B=[],arr=[...(CTX.employees||[])].filter(e=>e.status!=="left").sort((a,b)=>String(a.nameEn).localeCompare(String(b.nameEn)));
  B.push({html:`<div class="fr-top"><span class="eyebrow">Staff list</span><span>As of <b>${fmtDate(today(),"en")}</b></span></div><div class="fr-title">Our Employees</div><div class="fr-sub">${arr.length} employee${arr.length===1?"":"s"} currently working</div>`});
  const head=`<thead><tr><th style="width:8mm">#</th><th>Name</th><th>Position</th><th style="width:24mm">Nationality</th><th style="width:30mm">Qatar ID</th><th style="width:24mm">ID valid until</th><th style="width:24mm">Joined</th></tr></thead>`;
  arr.forEach((e,i)=>{const st=qidState(e);B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td><b>${esc(e.nameEn)}</b>${e.nameAr?`<small style="display:block;color:#8a93a6">${esc(e.nameAr)}</small>`:""}</td><td>${esc(e.posEn||"")}</td><td>${esc(e.natEn||"")}</td><td class="num">${esc(e.qid||"")}</td><td>${esc(e.qidExp||"")}${st?` <span class="vstat ${st[0]}">${st[0]==="over"?"Expired":"Soon"}</span>`:""}</td><td>${e.joinDate?fmtDate(e.joinDate,"en"):""}</td></tr>`,table:"sl2",tcls:"ft",head})});
  if(!arr.length)B.push({html:`<p style="color:#8a93a6">No employees saved yet.</p>`});return{dir:"ltr",blocks:B}};
const DOCNAME=k=>(DOCS.find(x=>x[0]===k)||[k,k])[1];
const fmtAt=a=>{const d=new Date(a);return isNaN(d)?"":`${fmtDate(a.slice(0,10),"en")}, ${String(d.getHours()).padStart(2,"0")}:${String(d.getMinutes()).padStart(2,"0")}`};
const DOCS=[["embryo","Embryo Transfer"],["letter","Custom Letter"],["idcard","Staff ID Cards"],["horses","All Horses"],["profile","Horse Profile"],["staff","All Employees"],["fin","Financial Report"],["po","Purchase Order"],["inv","Invoice / Receipt"],["pay","Payslips"],["diet","Diet Log"],["vet","Vet Record"],["cover","Covering"],["transfer","Horse Transfer"],["board","Boarding"],["sal","Salary Certificate"],["letters","Staff Letters"],["offer","Employment Offer"],["rem","Reminders"],["reg","Register"]];
const TGROUPS=[["Office",["fin","po","inv","pay","letter"]],["Horses",["horses","profile","diet","vet","cover","transfer","board","embryo"]],["Staff",["staff","idcard","sal","letters","offer"]],["Tools",["rem","reg"]]];
const HSTAT=[["stud","At the stud"],["boarding","Boarding (owner's horse)"],["sold","Sold"],["leased","Leased"],["deceased","Deceased"]];
const ESTAT=[["active","Working"],["leave","On leave"],["left","Left the company"]];
function qidState(e){const iso=toISO(e.qidExp);if(!iso)return null;const t=today();if(iso<t)return["over","ID expired"];if(iso<=addDays(t,60))return["soon","ID expires soon"];return null}
function qrSvg(text){try{const q=qrcode(0,"M");q.addData(text);q.make();const n=q.getModuleCount();let p="";for(let r=0;r<n;r++)for(let c=0;c<n;c++)if(q.isDark(r,c))p+=`M${c} ${r}h1v1h-1z`;return `<svg viewBox="-1 -1 ${n+2} ${n+2}" xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges"><rect x="-1" y="-1" width="${n+2}" height="${n+2}" fill="#fff"/><path d="${p}" fill="#1d2433"/></svg>`}catch(e){return""}}
R.idcard=d=>{const B=[],pk=(d.pick||[]).map(low),list=(CTX.employees||[]).filter(e=>pk.includes(low(e.nameEn)));
  const card=e=>{const ph=e.photo?`<img src="${e.photo}" alt="">`:`<div class="idc-noph">${esc((e.nameEn||"?").trim().split(/\s+/).map(w=>w[0]).slice(0,2).join("").toUpperCase())}</div>`;
    const qrt=`SK Arabian for Trading | ${e.nameEn||""} | ${e.empNo||""}${d.showQid==="yes"&&e.qid?" | QID "+e.qid:""} | Tel ${LH.mob}`;
    const front=`<div class="idc idc-front"><div class="idc-band"><img src="${LOGO}" alt=""><div class="idc-tt"><b>${esc(d.title)}</b><span>${esc(d.titleAr)}</span></div></div>
      <div class="idc-body"><div class="idc-ph">${ph}</div><div class="idc-info"><div class="idc-n">${esc(e.nameEn||"")}</div><div class="idc-na">${esc(e.nameAr||"")}</div><div class="idc-pos">${esc(e.posEn||"")}${e.posAr?` <span>· ${esc(e.posAr)}</span>`:""}</div>
      <div class="idc-meta"><span><small>ID No.</small><b>${esc(e.empNo||"")}</b></span><span><small>Valid until</small><b>${d.expiry?fmtDate(d.expiry,"en"):""}</b></span></div></div></div></div>`;
    const rows=[["Nationality",e.natEn,"الجنسية"],d.showQid==="yes"?["Qatar ID",e.qid,"الرقم الشخصي"]:null,["Blood group",e.blood,"فصيلة الدم"],["Emergency",e.emergency,"الطوارئ"],["Issued",d.issue?fmtDate(d.issue,"en"):"","تاريخ الإصدار"]].filter(x=>x&&String(x[1]||"").trim());
    const back=`<div class="idc idc-back"><img class="idc-wm" src="${MARK}" alt=""><div class="idc-bk"><table>${rows.map(r=>`<tr><td>${r[0]}</td><td><b>${esc(r[1])}</b></td></tr>`).join("")}</table><div class="idc-qr">${qrSvg(qrt)}</div></div>
      <div class="idc-note">This card is the property of SK Arabian for Trading. If found, please return to P.O.Box ${LH.pobox} · Tel ${LH.mob}<br><span dir="rtl">هذه البطاقة ملك لـ اس كي ارابيان للتجارة، يرجى إعادتها عند العثور عليها.</span></div>
      <div class="idc-sig"><span>Holder's signature</span><span>Authorized signature</span></div></div>`;
    return d.output==="a4"?`<div class="idpair">${front}${back}</div>`:[front,back]};
  if(d.output!=="a4"){const cards=[];list.forEach(e=>{const [f,b]=card(e);cards.push(f,b)});return{dir:"ltr",cards}}
  if(!list.length)B.push({html:`<p style="color:#8a93a6;font-size:11pt">Tick the employees to print in the form.</p>`});
  list.forEach(e=>B.push({html:card(e)}));
  return{dir:"ltr",bare:true,noFoot:true,blocks:B}};
/* ---------- defaults for the documents added in the Management System ---------- */
const _baseDefaults=defaults;
defaults=function(){const D=_baseDefaults();
 D.embryo={lang:"en",no:"",date:today(),code:"",name:"",flushDate:"",grade:"",stage:"",status:"fresh",storage:"",ownerEn:"SK Arabian for Trading",ownerAr:"اس كي ارابيان للتجارة",
   donor:"",donorReg:"",donorSire:"",donorDam:"",sire:"",sireReg:"",recipient:"",recipientReg:"",transferDate:"",expected:"",checks:[],vet:"",notes:"",notesAr:""};
 D.letter={lang:"en",ref:"",date:today(),toEn:"",toAr:"",subjectEn:"",subjectAr:"",bodyEn:"",bodyAr:"",signName:"",signTitleEn:"Authorized Signatory",signTitleAr:"المفوض بالتوقيع"};
 D.rem={window:"60"};D.reg={filter:"all",q:""};D.horses={};D.staff={};
 D.letters.decision="";
 return D};

/* ---------- reminders & register (from the Management System) ---------- */
const REMCAT={custom:"Your reminders",vet:"Vet & farrier",qid:"Staff IDs & passports",contract:"Contracts to renew",invoice:"Unpaid invoices",foal:"Breeding"};
R.rem=d=>{const r=CTX.reminders||[],B=[];
  B.push({html:`<div class="fr-top"><span class="eyebrow">Reminders</span><span>As of <b>${fmtDate(today(),"en")}</b> · next <b>${esc(d.window)} days</b></span></div><div class="fr-title">What's coming up</div><div class="fr-sub">${r.filter(x=>x.status==="over").length} overdue · ${r.filter(x=>x.status==="soon").length} due soon</div>`});
  const head=`<thead><tr><th style="width:30mm">Date</th><th style="width:34mm">Category</th><th>Item</th><th class="c" style="width:24mm">Status</th></tr></thead>`;
  r.forEach(x=>B.push({row:`<tr><td class="num">${fmtDate(x.date,"en")}</td><td>${REMCAT[x.cat]||esc(x.cat)}</td><td><b>${esc(x.title)}</b>${x.detail?`<small style="display:block;color:#8a93a6">${esc(x.detail)}</small>`:""}</td><td class="c"><span class="vstat ${x.status==="over"?"over":"soon"}">${x.status==="over"?"Overdue":"Due soon"}</span></td></tr>`,table:"rem",tcls:"ft",head}));
  if(!r.length)B.push({html:`<p style="color:#8a93a6">Nothing due in the next ${esc(d.window)} days.</p>`});
  return{dir:"ltr",blocks:B}};
R.reg=d=>{const list=CTX.register||[],B=[];
  B.push({html:`<div class="fr-top"><span class="eyebrow">Document register</span><span>Printed on <b>${fmtDate(today(),"en")}</b></span></div><div class="fr-title">Document Register</div><div class="fr-sub">${d.filter==="all"||!d.filter?"All documents":DOCNAME(d.filter)}${d.q?` matching “${esc(d.q)}”`:""} · ${list.length} ${list.length===1?"entry":"entries"}</div>`});
  const head=`<thead><tr><th style="width:9mm">#</th><th style="width:40mm">Printed</th><th style="width:38mm">Document</th><th>Number / name</th><th style="width:28mm">Doc date</th></tr></thead>`;
  list.forEach((e,i)=>B.push({row:`<tr><td class="idx num">${String(i+1).padStart(2,"0")}</td><td class="num">${fmtAt(e.at)}</td><td>${esc(DOCNAME(e.doc))}</td><td><b>${esc(e.ref)}</b>${e.title?`<small style="display:block;color:#8a93a6">${esc(e.title)}</small>`:""}</td><td>${esc(e.date?fmtDate(e.date,"en"):"")}</td></tr>`,table:"reg",tcls:"ft",head}));
  if(!list.length)B.push({html:`<p style="color:#8a93a6">Nothing printed yet. Every time you print a document it is listed here.</p>`});
  return{dir:"ltr",blocks:B}};

/* ---------- Embryo transfer record (new in the Management System, same visual language) ---------- */
const EST={fresh:["Fresh","طازج"],frozen:["Frozen / stored","مجمّد / مخزّن"],transferred:["Transferred","منقول"],pregnant:["Recipient in foal","المستقبِلة عشار"],foaled:["Foaled","وُلد"],failed:["Failed","فشل"]};
const CHK={scheduled:["Scheduled","مجدول"],positive:["Positive (in foal)","إيجابي (عشار)"],negative:["Negative","سلبي"],inconclusive:["Inconclusive","غير حاسم"]};
R.embryo=d=>{const ar=d.lang==="ar",L=(e,a)=>ar?a:e,B=[],st=EST[d.status]||["",""];
  B.push({html:`<div class="tr-ref"><span>${L("Record No.","رقم السجل")}: <b class="num">${esc(d.no)}</b></span><span>${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang)}</b></span></div>`});
  B.push({html:`<div class="tr-title"><h1>${L("Embryo Transfer Record","سجل نقل الأجنة")}</h1><div class="sub num">${esc(d.code)}${d.name?" · "+esc(d.name):""}</div></div>`});
  B.push({html:`<div class="sech">${L("Embryo","الجنين")}</div><table class="kv">
   <tr><td class="l">${L("Embryo code","رمز الجنين")}</td><td class="v num">${esc(d.code)}</td><td class="l">${L("Status","الحالة")}</td><td class="v">${ar?st[1]:st[0]}</td></tr>
   <tr><td class="l">${L("Flush / collection date","تاريخ الجمع")}</td><td class="v">${fmtDate(d.flushDate,d.lang)}</td><td class="l">${L("Grade / stage","الدرجة / المرحلة")}</td><td class="v">${esc([d.grade,d.stage].filter(Boolean).join(" · "))}</td></tr>
   <tr><td class="l">${L("Storage","التخزين")}</td><td class="v">${esc(d.storage)}</td><td class="l">${L("Owner","المالك")}</td><td class="v">${esc(ar?d.ownerAr:d.ownerEn)}</td></tr></table>`});
  B.push({html:`<div class="parties"><div class="party"><h4>${L("Donor mare","الفرس المانحة")}</h4><div class="nm">${esc(d.donor)}</div><dl>${[[L("Passport / Reg. No.","رقم الجواز / التسجيل"),d.donorReg],[L("Sire","الأب"),d.donorSire],[L("Dam","الأم"),d.donorDam]].filter(x=>x[1]).map(x=>`<dt>${x[0]}</dt><dd>${esc(x[1])}</dd>`).join("")}</dl></div>
   <div class="party"><h4>${L("Sire","الفحل")}</h4><div class="nm">${esc(d.sire)}</div><dl>${[[L("Passport / Reg. No.","رقم الجواز / التسجيل"),d.sireReg]].filter(x=>x[1]).map(x=>`<dt>${x[0]}</dt><dd>${esc(x[1])}</dd>`).join("")}</dl></div></div>`});
  B.push({html:`<div class="sech">${L("Transfer","النقل")}</div><table class="kv">
   <tr><td class="l">${L("Recipient mare","الفرس المستقبِلة")}</td><td class="v">${esc(d.recipient)}</td><td class="l">${L("Passport / Reg. No.","رقم الجواز / التسجيل")}</td><td class="v num">${esc(d.recipientReg)}</td></tr>
   <tr><td class="l">${L("Transfer date","تاريخ النقل")}</td><td class="v">${fmtDate(d.transferDate,d.lang)}</td><td class="l">${L("Veterinarian","الطبيب البيطري")}</td><td class="v">${esc(d.vet)}</td></tr></table>`});
  const checks=(d.checks||[]).filter(c=>c.date);
  if(checks.length){const head=`<thead><tr><th style="width:32mm">${L("Check date","تاريخ الفحص")}</th><th style="width:44mm">${L("Result","النتيجة")}</th><th>${L("Notes","ملاحظات")}</th></tr></thead>`;
    checks.forEach(c=>B.push({row:`<tr><td>${fmtDate(c.date,d.lang)}</td><td><b>${(CHK[c.result]||["",""])[ar?1:0]}</b></td><td>${esc(c.notes)}</td></tr>`,table:"ech",tcls:"ft",head}))}
  if(d.expected)B.push({html:`<div class="foal"><span>${L("Expected foaling","الولادة المتوقعة")}</span><b>${fmtDate(d.expected,d.lang)}</b></div>`});
  const nt=ar?(d.notesAr||d.notes):d.notes;if((nt||"").trim())B.push({html:`<div class="terms"><h4>${L("Notes","ملاحظات")}</h4><div>${esc(nt)}</div></div>`});
  B.push({html:`<div class="sigs three" style="padding-top:14mm"><div class="sig">${L("Veterinarian","الطبيب البيطري")}${d.vet?`<b>${esc(d.vet)}</b>`:""}</div><div class="sig">${L("Stud manager","مدير المربط")}</div><div class="sig">${L("Stud stamp","ختم المربط")}</div></div>`});
  return{dir:ar?"rtl":"ltr",blocks:B}};

/* ---------- Custom letter on letterhead ---------- */
R.letter=d=>{const ar=d.lang==="ar",L=(e,a)=>ar?a:e,B=[];
  B.push({html:`<div class="tr-ref"><span>${L("Ref","المرجع")}: <b class="num">${esc(d.ref)}</b></span><span>${L("Date","التاريخ")}: <b>${fmtDate(d.date,d.lang)}</b></span></div>`});
  const to=ar?d.toAr:d.toEn;if((to||"").trim())B.push({html:`<div class="of-p">${esc(to).replace(/\n/g,"<br>")}</div>`});
  const sj=ar?d.subjectAr:d.subjectEn;if((sj||"").trim())B.push({html:`<div class="cl-h"><small>${L("Subject","الموضوع")}</small><b>${esc(sj)}</b></div>`});
  B.push({html:`<div class="of-p">${para(ar?d.bodyAr:d.bodyEn)}</div>`});
  B.push({html:`<div class="sc-sign"><b>${esc(d.signName)}</b><div>${esc(ar?d.signTitleAr:d.signTitleEn)}</div></div>`});
  return{dir:ar?"rtl":"ltr",serif:true,blocks:B}};

/* ---------- letterhead page & pagination (same engine as the original Studio) ---------- */
function headHTML(meta){
  const v=+(meta.letterhead||2);
  const logo=v===1?`<div class="lh-v1"><img src="${MARK}" alt="SK Arabian"><span>Sk.Arabian</span></div>`:`<img src="${LOGO}" alt="SK Arabian">`;
  let right="";
  if(meta.ref||meta.verifyUrl){right=`<div class="vq">${meta.ref?`<div class="meta"><span class="num">${esc(meta.ref)}</span>${meta.issued?`<br>${esc(meta.issued)}`:""}</div>`:""}${meta.verifyUrl?`<div class="vqr" title="Scan to verify">${qrSvg(meta.verifyUrl)}</div>`:""}</div>`}
  return `<div class="lh-head">${logo}${right}</div>`}
function newPage(R,host,meta){
  const el=document.createElement("div");
  el.className="page"+(R.serif?" serif":"")+(R.noFoot?" nofoot":"")+(R.bare?" bare":"")+(R.cls?" "+R.cls:"");el.dir=R.dir;el.lang=R.dir==="rtl"?"ar":"en";
  el.innerHTML=R.bare?`<div class="pbody"><div class="flow"></div></div>`:`<img class="lh-wm" src="${MARK}" alt="">
  ${headHTML(meta)}
  <div class="pbody"><div class="flow"></div></div>
  ${R.noFoot?"":`<div class="lh-note"><span>${R.note||""}</span><span class="pnum"></span></div>
  <div class="lh-foot"><span>C.R.: ${esc(LH.cr)},</span><span>Mob.: ${esc(LH.mob)},</span><span>Email: ${esc(LH.email)},</span><span>P.O.Box: ${esc(LH.pobox)}</span></div>`}`;
  host.appendChild(el);
  return {el,body:el.querySelector(".pbody"),flow:el.querySelector(".flow")};
}
function over(p){return p.flow.offsetHeight>p.body.clientHeight+1}
function layout(R,host,meta){
  host.innerHTML="";
  if(R.cards){if(!R.cards.length)host.innerHTML=`<div class="page cardpage cardempty">Tick the employees to print in the form.</div>`;R.cards.forEach(h=>{const el=document.createElement("div");el.className="page cardpage";el.innerHTML=h;host.appendChild(el)});return}
  if(R.fitOne){const p=newPage(R,host,meta);
    R.blocks.forEach(b=>{if(b.row){let t=p.flow.lastElementChild;if(!t||t.dataset.tid!==b.table){t=document.createElement("table");t.className=b.tcls||"";t.dataset.tid=b.table;t.innerHTML=(b.head||"")+"<tbody></tbody>";p.flow.appendChild(t)}t.tBodies[0].insertAdjacentHTML("beforeend",b.row)}else{const dv=document.createElement("div");dv.className="blk";dv.innerHTML=b.html;p.flow.appendChild(dv)}});
    const H=p.body.clientHeight,h=p.flow.offsetHeight;
    if(h>H){const sc=H/h;if(sc<0.6){return layout(Object.assign({},R,{fitOne:false}),host,meta)}
      p.flow.style.transform=`scale(${sc.toFixed(4)})`;p.flow.style.transformOrigin=R.dir==="rtl"?"top right":"top left";p.flow.style.width=(100/sc).toFixed(3)+"%";if(R.dir==="rtl"){p.flow.style.marginLeft="auto";p.flow.style.position="absolute";p.flow.style.right="0"}}
    const pn=p.el.querySelector(".pnum");if(pn)pn.textContent=R.dir==="rtl"?"صفحة 1 من 1":"Page 1 of 1";return}
  let p=newPage(R,host,meta);
  const place=(b,retry)=>{
    if(b.row){
      let t=p.flow.lastElementChild;
      if(!t||t.dataset.tid!==b.table){t=document.createElement("table");t.className=b.tcls||"";t.dataset.tid=b.table;t.innerHTML=(b.head||"")+"<tbody></tbody>";p.flow.appendChild(t)}
      const tb=t.tBodies[0];tb.insertAdjacentHTML("beforeend",b.row);
      if(over(p)&&!retry){tb.lastElementChild.remove();if(!tb.children.length)t.remove();p=newPage(R,host,meta);place(b,true)}
    }else{
      const d=document.createElement("div");d.className="blk";d.innerHTML=b.html;p.flow.appendChild(d);
      if(over(p)&&p.flow.children.length>1&&!retry){d.remove();p=newPage(R,host,meta);place(b,true)}
    }
  };
  R.blocks.forEach(b=>{place(b,false);if(b.breakAfter)p=newPage(R,host,meta)});
  const pages=[...host.querySelectorAll(".page")];
  pages.forEach((pg,i)=>{const pn=pg.querySelector(".pnum");if(pn)pn.textContent=R.dir==="rtl"?`صفحة ${i+1} من ${pages.length}`:`Page ${i+1} of ${pages.length}`});
}
function setPageSize(card){let st=document.getElementById("pgsize");if(!st){st=document.createElement("style");st.id="pgsize";document.head.appendChild(st)}st.textContent=card?"@page{size:85.6mm 54mm;margin:0}":"@page{size:A4;margin:0}"}

/* Public API */
window.SKStudio={
  types:DOCS,groups:TGROUPS,
  defaults:()=>defaults(),
  clean:(doc,prev)=>{const D=defaults()[doc];if(!D)return{};if(typeof cleanNew==="function"&&_baseDefaults()[doc]){try{return cleanNew(doc,prev||D)}catch(e){}}return clone(D)},
  setContext:c=>{CTX=c||{}},
  build:(doc,data)=>R[doc](data),
  render:(host,doc,data,meta)=>{const r=R[doc](data);setPageSize(!!r.cards);layout(r,host,meta||{});return r},
  helpers:{esc,money,int,n2,fmtDate,fmtMonth,today,addDays,addMonths,dayDiff,wordsEn,wordsAr,payNet,vetUpcoming,vetStatus,foalDate,lastCover,boardEnd,eosCalc,lenText,pedLabel,MEN,MAR,SEX,VT,METH,PREG,INC,LEAVE,EOSR,LTYPE,HSTAT,PLANST,EMBST,PEDGEN,CUR,EST,CHK,low,clone}
};
})();
