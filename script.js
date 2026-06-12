// â•â•â•â•â•â• UTILITIES â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
const SPENDWISE_BUILD='2026-06-01-ui-fixes';
const I18N_MAP=Object.assign({},window.SPENDWISE_I18N_AM||{},window.SPENDWISE_I18N_EXTRA||{});
const I18N_LANG_KEY='sw_lang';
function getLang(){try{return getState()?.language||localStorage.getItem(I18N_LANG_KEY)||'en'}catch{return localStorage.getItem(I18N_LANG_KEY)||'en'}}
function setLang(lang){
  const code=lang==='am'?'am':'en';
  localStorage.setItem(I18N_LANG_KEY,code);
  try{if(typeof getState==='function'&&typeof dispatch==='function')dispatch({type:'SET_LANGUAGE',payload:code})}catch{}
  applyDocumentLang(code);
}
function applyDocumentLang(lang){
  const code=lang||getLang();
  document.documentElement.lang=code==='am'?'am':'en';
}
function t(text,vars){
  if(text==null||text==='')return text||'';
  let out=getLang()==='am'?(I18N_MAP[text]||text):text;
  if(vars)for(const[k,v]of Object.entries(vars))out=out.split('{{'+k+'}}').join(String(v));
  return out;
}
function tCat(name){return t(name)}
function tFreq(freq){return t(FREQ_LABELS[freq]||freq)}
function tBillCat(cat){const label=cat.charAt(0).toUpperCase()+cat.slice(1);return t(label)}
function tGreet(){
  const g=greet();
  return t(g==='morning'?'Good morning':g==='afternoon'?'Good afternoon':'Good evening');
}
function tUiMsg(msg){return t(msg)}
function confirmT(msg){return confirm(t(msg))}
function alertT(msg){return alert(t(msg))}
const APP_LOGO_SRC='assets/spendwise-logo.png';
function appLogo(height,extraStyle={}){
  const img=el('img',{src:APP_LOGO_SRC,alt:'SpendWise'});
  img.style.height=height;
  img.style.width='auto';
  img.style.maxWidth='100%';
  img.style.objectFit='cover';
  img.style.display='block';
  Object.assign(img.style,extraStyle);
  return img;
}
function appBrand(opts={}){
  const height=opts.height||'2rem';
  const showName=opts.showName!==false;
  const compact=!!opts.compact;
  const wrap=el('a',{
    href:'#',
    cls:'app-brand'+(compact?' app-brand--compact':'')+(opts.cls?' '+opts.cls:''),
    style:{flexShrink:0,textDecoration:'none'},
    onclick:e=>{e.preventDefault();if(typeof opts.onClick==='function')opts.onClick();else navigate('dashboard')}
  });
  const mark=el('div',{cls:'app-brand__mark'});
  mark.appendChild(appLogo(height));
  wrap.appendChild(mark);
  if(showName)wrap.appendChild(el('span',{cls:'app-brand__name'},'SpendWise'));
  return wrap;
}
function generateId(){return crypto.randomUUID?crypto.randomUUID():Date.now().toString(36)+Math.random().toString(36).slice(2,9)}
function todayStr(){const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function currentMonthStr(){const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')}
function fmtDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function subDays(n){const d=new Date();d.setDate(d.getDate()-n);return d}
function addDays(n){const d=new Date();d.setDate(d.getDate()+n);return d}
function addMonths(n){const d=new Date();d.setMonth(d.getMonth()+n);return d}
function advanceByFrequency(dateStr,freq){
  const d=new Date(dateStr+'T00:00:00');
  if(freq==='daily')d.setDate(d.getDate()+1);
  else if(freq==='weekly')d.setDate(d.getDate()+7);
  else if(freq==='monthly')d.setMonth(d.getMonth()+1);
  else if(freq==='yearly')d.setFullYear(d.getFullYear()+1);
  return fmtDate(d)
}
function formatETB(n,dec=0){return 'ETB '+Number(n).toFixed(dec)}
function formatCompact(n){if(n>=1e6)return 'ETB '+(n/1e6).toFixed(1)+'M';if(n>=1000)return 'ETB '+(n/1000).toFixed(1)+'k';return 'ETB '+Number(n).toFixed(0)}
function isValidAmount(v){const n=parseFloat(v);return isFinite(n)&&n>0}
function isValidDate(v){return v&&!isNaN(new Date(v+'T00:00:00').getTime())}
function hashPw(p){let h=0;for(let i=0;i<p.length;i++)h=Math.imul(31,h)+p.charCodeAt(i)|0;return 'h'+Math.abs(h)}
function greet(){const h=new Date().getHours();return h<12?'morning':h<17?'afternoon':'evening'}
function fmtMonthLabel(m){const loc=getLang()==='am'?'am-ET':'en';return new Date(m+'-01T00:00:00').toLocaleDateString(loc,{month:'long',year:'numeric'})}
function subMonth(m){const d=new Date(m+'-01T00:00:00');d.setMonth(d.getMonth()-1);return fmtDate(d).slice(0,7)}
function addMonth(m){const d=new Date(m+'-01T00:00:00');d.setMonth(d.getMonth()+1);return fmtDate(d).slice(0,7)}
function daysUntilInfo(dateStr){
  const diff=Math.round((new Date(dateStr+'T00:00:00').getTime()-new Date(todayStr()+'T00:00:00').getTime())/86400000);
  if(diff<0)return{label:Math.abs(diff)+t('d overdue'),color:'#dc2626'};
  if(diff===0)return{label:t('due today'),color:'#ea580c'};
  if(diff===1)return{label:t('due tomorrow'),color:'#f97316'};
  return{label:t('due in ')+diff+'d',color:'#64748b'}
}
function billAttentionLabel(count){
  const unit=count>1?t('bills'):t('bill');
  return count+unit+t(' need attention');
}
function getCatClass(cat){const m={'Food':'cat-Food','Transport':'cat-Transport','Entertainment':'cat-Entertainment','Health':'cat-Health','Utilities':'cat-Utilities','Shopping':'cat-Shopping','Rent':'cat-Rent','Education':'cat-Education','Personal Care':'cat-Personal','Other':'cat-Other'};return m[cat]||'cat-Other'}

// DOM builder
function el(tag,props={},children=[]){
  const e=document.createElement(tag);
  for(const[k,v]of Object.entries(props)){
    if(k==='cls')e.className=v;
    else if(k==='style'&&typeof v==='object')Object.assign(e.style,v);
    else if(k==='html')e.innerHTML=v;
    else if(k.startsWith('on'))e.addEventListener(k.slice(2).toLowerCase(),v);
    else if((k==='placeholder'||k==='title'||k==='alt')&&typeof v==='string')e.setAttribute(k,t(v));
    else e.setAttribute(k,v);
  }
  for(const c of [children].flat()){
    if(c==null||c===false)continue;
    e.appendChild(typeof c==='string'?document.createTextNode(t(c)):c);
  }
  return e;
}
function ic(name,sz=18,cls=''){
  const s=document.createElementNS('http://www.w3.org/2000/svg','svg');
  s.setAttribute('width',sz);s.setAttribute('height',sz);s.setAttribute('viewBox','0 0 24 24');
  s.setAttribute('fill','none');s.setAttribute('stroke','currentColor');s.setAttribute('stroke-width','2');
  s.setAttribute('stroke-linecap','round');s.setAttribute('stroke-linejoin','round');
  if(cls)s.setAttribute('class',cls);s.dataset.lucide=name;return s;
}
function renderIcons(root){if(window.lucide)window.lucide.createIcons({nameAttr:'data-lucide',elements:root?[root]:undefined})}
function Toggle(checked,onChange,color='teal'){
  const btn=el('button',{cls:'toggle-track'+(checked?' on':'')+(color==='blue'?' blue':''),type:'button'});
  btn.appendChild(el('div',{cls:'toggle-thumb'}));
  btn.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();onChange(!checked)});return btn;
}
function ProgressBar(pct,isOver,isWarn){
  const bar=el('div',{cls:'progress-bar'});
  const fill=el('div',{cls:'progress-fill '+(isOver?'over':isWarn?'warning':'ok')});
  fill.style.width=Math.min(pct,100)+'%';bar.appendChild(fill);return bar;
}
const BILL_LUCIDE={electricity:'zap',water:'droplet',internet:'wifi',tv:'tv',other:'file-text'};
function billCategoryIcon(cat,sz=18){
  const box=el('div',{style:{display:'flex',alignItems:'center',justifyContent:'center'}});
  const i=ic(BILL_LUCIDE[cat]||'file-text',sz);
  i.style.color='#0d9488';
  box.appendChild(i);
  return box;
}
function buildCategoryField(categories,category,onChange,label='Category',errCls=''){
  const wrap=el('div');
  wrap.appendChild(el('label',{cls:'label'},label));
  const list=categories||CATEGORIES;
  const isCustom=category&&!list.includes(category);
  const selVal=isCustom?'Other':(category||list[0]);
  const sel=el('select',{cls:'input'+errCls});
  list.forEach(c=>{const o=el('option',{value:c},c);if(c===selVal)o.selected=true;sel.appendChild(o)});
  const customWrap=el('div',{style:{display:selVal==='Other'?'block':'none',marginTop:'.5rem'}});
  const customIn=el('input',{cls:'input',placeholder:'Enter category name'});
  customIn.value=isCustom?category:'';
  function sync(){
    if(sel.value==='Other'){customWrap.style.display='block';onChange(customIn.value.trim()||'Other')}
    else{customWrap.style.display='none';onChange(sel.value)}
  }
  sel.addEventListener('change',sync);
  customIn.addEventListener('input',()=>{if(sel.value==='Other')onChange(customIn.value.trim()||'Other')});
  customWrap.appendChild(customIn);
  wrap.append(sel,customWrap);
  return wrap;
}
function buildBillCategoryField(category,onChange){
  const wrap=el('div');
  wrap.appendChild(el('label',{cls:'label'},'Category'));
  const sel=el('select',{cls:'input'});
  BILL_CATS.forEach(c=>{const o=el('option',{value:c},tBillCat(c));if(c===category)o.selected=true;sel.appendChild(o)});
  const customWrap=el('div',{style:{display:category==='other'?'block':'none',marginTop:'.5rem'}});
  const customIn=el('input',{cls:'input',placeholder:'Enter bill type'});
  customIn.addEventListener('input',()=>{if(sel.value==='other')onChange('other')});
  sel.addEventListener('change',e=>{const v=e.target.value;customWrap.style.display=v==='other'?'block':'none';onChange(v)});
  customWrap.appendChild(customIn);
  wrap.append(sel,customWrap);
  return wrap;
}
function emailNotificationsEnabled(s){return s.notifications?.email!==false}
function setEmailNotifications(enabled){
  dispatch({type:'SET_NOTIFICATIONS',payload:{email:!!enabled}});
}

// CONSTANTS
const CATEGORIES=['Food','Transport','Entertainment','Health','Utilities','Shopping','Rent','Education','Personal Care','Other'];
const FREQ_LABELS={daily:'Daily',weekly:'Weekly',monthly:'Monthly',yearly:'Yearly'};
const FREQ_BADGE={daily:'badge badge-blue',weekly:'badge badge-orange',monthly:'badge badge-slate',yearly:'badge badge-green'};
const BILL_CATS=['electricity','water','internet','tv','other'];
const CHART_COLORS=['#0d9488','#f97316','#8b5cf6','#06b6d4','#ec4899','#84cc16','#f59e0b','#6366f1','#10b981','#ef4444'];
const PAGE_SIZE=15;

// â•â•â•â•â•â• AUTH â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
const API_ENDPOINT='functions.php';
const BOOT=window.__SPENDWISE_BOOT__||{};
const GOOGLE_CONFIG=BOOT.google||{};
let AUTH_SESSION=BOOT.user||null;
let SAVE_TIMER=null;
let SAVE_PENDING=null;
let GOOGLE_ID_API=null;
let GOOGLE_ID_READY=null;
let GOOGLE_CALLBACK=null;

async function apiRequest(action,payload={}){
  const response=await fetch(API_ENDPOINT+'?action='+encodeURIComponent(action),{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json'},
    credentials:'same-origin',
    body:JSON.stringify(payload)
  });
  const text=await response.text();
  let parsed={};
  if(text){
    try{parsed=JSON.parse(text)}catch{throw new Error(text||'Unexpected server response.')}
  }
  if(!response.ok||parsed.ok===false){
    if(response.status===401)AUTH_SESSION=null;
    throw new Error(parsed.error||'Request failed.');
  }
  return parsed.data??parsed;
}

function syncSession(user){AUTH_SESSION=user||null;return AUTH_SESSION}
function applySessionToState(state){
  if(!AUTH_SESSION)return state;
  return{
    ...state,
    user:{
      ...state.user,
      name:AUTH_SESSION.name||state.user.name,
      email:AUTH_SESSION.email||state.user.email,
      phone:AUTH_SESSION.phone??state.user.phone,
      avatar:AUTH_SESSION.avatar??state.user.avatar
    }
  };
}
function syncState(state){S=applySessionToState(mergeState(state));document.documentElement.classList.toggle('dark',S.darkMode);return S}

function googleEnabled(){return !!(GOOGLE_CONFIG.enabled&&GOOGLE_CONFIG.clientId)}
function googleClientId(){return GOOGLE_CONFIG.clientId||''}
function setGoogleCredentialHandler(handler){GOOGLE_CALLBACK=handler}
function loadGoogleIdentityApi(){
  if(!googleEnabled())return Promise.reject(new Error('Google Sign-In is not configured.'));
  if(GOOGLE_ID_API)return Promise.resolve(GOOGLE_ID_API);
  if(GOOGLE_ID_READY)return GOOGLE_ID_READY;
  GOOGLE_ID_READY=new Promise((resolve,reject)=>{
    let attempts=0;
    const waitForGoogle=()=>{
      const api=window.google?.accounts?.id;
      if(api){
        try{
          api.initialize({
            client_id:googleClientId(),
            callback:response=>{if(typeof GOOGLE_CALLBACK==='function')GOOGLE_CALLBACK(response)}
          });
        }catch(err){
          GOOGLE_ID_READY=null;
          reject(err instanceof Error?err:new Error('Google Sign-In failed to initialize.'));
          return;
        }
        GOOGLE_ID_API=api;
        resolve(api);
        return;
      }
      attempts++;
      if(attempts>=50){
        GOOGLE_ID_READY=null;
        reject(new Error('Google Sign-In failed to load. Check your internet connection and Google script access.'));
        return;
      }
      setTimeout(waitForGoogle,200);
    };
    waitForGoogle();
  });
  return GOOGLE_ID_READY;
}
async function renderGoogleIdentityButton(container){
  if(!container)return;
  const api=await loadGoogleIdentityApi();
  if(!container.isConnected)return;
  container.innerHTML='';
  api.renderButton(container,{
    theme:'outline',
    size:'large',
    shape:'pill',
    text:'continue_with',
    width:Math.max(240,Math.min(360,container.clientWidth||320))
  });
}

const Auth={
  session(){return AUTH_SESSION},
  async login(email,pw){
    const data=await apiRequest('login',{email,password:pw});
    syncSession(data.user??null);syncState(data.state??null);return AUTH_SESSION;
  },
  async signup(name,email,pw){
    const data=await apiRequest('signup',{name,email,password:pw});
    syncSession(data.user??null);syncState(data.state??null);return AUTH_SESSION;
  },
  async googleLogin(credential){
    const data=await apiRequest('google_login',{credential});
    syncSession(data.user??null);syncState(data.state??null);return AUTH_SESSION;
  },
  async logout(){
    try{await apiRequest('logout')}catch(e){console.warn(e)}
    try{window.google?.accounts?.id?.disableAutoSelect?.()}catch(e){console.warn(e)}
    syncSession(null);syncState(null);
  },
  async generateOtp(email){
    return await apiRequest('send_otp',{email});
  },
  async verifyOtp(email,code){
    await apiRequest('verify_otp',{email,code});return true;
  },
  async resetPassword(email,pw){
    await apiRequest('reset_password',{email,password:pw});
  },
  async updateProfile(patch){
    const data=await apiRequest('update_profile',patch);
    syncSession(data.user??AUTH_SESSION);return AUTH_SESSION;
  }
};

// â•â•â•â•â•â• STATE â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
const SK='spendwise_state';
function makeSampleData(){
  const m=currentMonthStr();
  return{
    expenses:[
      {id:'1',amount:350,category:'Food',date:todayStr(),note:'Morning coffee',receipt:null},
      {id:'2',amount:800,category:'Transport',date:fmtDate(subDays(1)),note:'Taxi to office',receipt:null},
      {id:'3',amount:4500,category:'Food',date:fmtDate(subDays(1)),note:'Grocery shopping',receipt:null},
      {id:'4',amount:1200,category:'Entertainment',date:fmtDate(subDays(2)),note:'Movie ticket',receipt:null},
      {id:'5',amount:2500,category:'Health',date:fmtDate(subDays(3)),note:'Pharmacy',receipt:null},
      {id:'6',amount:6000,category:'Food',date:fmtDate(subDays(4)),note:'Restaurant dinner',receipt:null},
      {id:'7',amount:1500,category:'Transport',date:fmtDate(subDays(5)),note:'Bus pass top-up',receipt:null},
      {id:'8',amount:3000,category:'Shopping',date:fmtDate(subDays(6)),note:'Clothing store',receipt:null},
      {id:'9',amount:500,category:'Food',date:fmtDate(subDays(7)),note:'Lunch snack',receipt:null},
      {id:'10',amount:2200,category:'Entertainment',date:fmtDate(subDays(8)),note:'Netflix + Spotify',receipt:null},
      {id:'11',amount:1800,category:'Food',date:fmtDate(subDays(9)),note:'Pizza delivery',receipt:null},
      {id:'12',amount:50000,category:'Rent',date:fmtDate(subDays(10)),note:'Monthly rent',receipt:null},
    ],
    budgets:[
      {id:'b1',category:'Food',limit:20000,month:m},{id:'b2',category:'Transport',limit:10000,month:m},
      {id:'b3',category:'Entertainment',limit:5000,month:m},{id:'b4',category:'Health',limit:8000,month:m},
      {id:'b5',category:'Shopping',limit:15000,month:m},
    ],
    recurring:[
      {id:'r1',name:'Rent',amount:50000,category:'Rent',frequency:'monthly',startDate:fmtDate(subDays(90)),endDate:null,nextDue:fmtDate(addMonths(1)),active:true},
      {id:'r2',name:'Netflix',amount:1500,category:'Entertainment',frequency:'monthly',startDate:fmtDate(subDays(60)),endDate:null,nextDue:fmtDate(addDays(5)),active:true},
      {id:'r3',name:'Gym Membership',amount:3000,category:'Health',frequency:'monthly',startDate:fmtDate(subDays(45)),endDate:null,nextDue:fmtDate(addDays(12)),active:true},
      {id:'r4',name:'Weekly Groceries',amount:5000,category:'Food',frequency:'weekly',startDate:fmtDate(subDays(30)),endDate:null,nextDue:fmtDate(addDays(2)),active:true},
    ],
    bills:[
      {id:'bl1',name:'Electricity',amount:2500,dueDate:fmtDate(addDays(3)),status:'upcoming',paidDate:null,reference:null,category:'electricity'},
      {id:'bl2',name:'Internet',amount:4000,dueDate:fmtDate(addDays(1)),status:'upcoming',paidDate:null,reference:null,category:'internet'},
      {id:'bl3',name:'Water',amount:1200,dueDate:fmtDate(subDays(5)),status:'overdue',paidDate:null,reference:null,category:'water'},
      {id:'bl4',name:'TV Subscription',amount:1800,dueDate:fmtDate(subDays(10)),status:'paid',paidDate:fmtDate(subDays(11)),reference:'TLB-2024-001',category:'tv'},
    ],
  };
}
const INITIAL={
  ...makeSampleData(),
  categories:CATEGORIES.slice(),user:{name:'Alex',email:'',phone:'',avatar:null},
  darkMode:false,
  notifications:{email:true},
  language:localStorage.getItem(I18N_LANG_KEY)||'en'
};
function mergeState(raw){
  if(!raw||typeof raw!=='object')return{...INITIAL};
  const merged={...INITIAL,...raw};
  if(merged.notifications&&typeof merged.notifications.email!=='boolean'){
    const legacy=merged.notifications;
    merged.notifications={email:Object.values(legacy).some(ch=>ch&&typeof ch==='object'&&ch.email)};
  }
  delete merged.groups;
  return merged;
}
function loadState(){return applySessionToState(mergeState(BOOT.state))}
function saveState(s){
  if(!Auth.session())return;
  SAVE_PENDING=s;
  if(SAVE_TIMER)clearTimeout(SAVE_TIMER);
  SAVE_TIMER=setTimeout(async()=>{
    const snapshot=SAVE_PENDING;
    SAVE_PENDING=null;
    if(!snapshot||!Auth.session())return;
    try{await apiRequest('save_state',{state:snapshot})}catch(e){console.error('Failed to save state.',e)}
  },150);
}
let S=loadState();
applyDocumentLang(S.language);
const listeners=[];
function getState(){return S}
function sub(fn){listeners.push(fn);return()=>{const i=listeners.indexOf(fn);if(i>-1)listeners.splice(i,1)}}
function dispatch(action){S=reducer(S,action);saveState(S);listeners.forEach(fn=>{try{fn(S)}catch{}})}
async function clearAllData(){
  await apiRequest('clear_state');
  const empty={expenses:[],budgets:[],recurring:[],bills:[]};
  S=applySessionToState({...mergeState(null),...empty});
  document.documentElement.classList.toggle('dark',S.darkMode);
  listeners.forEach(fn=>{try{fn(S)}catch{}});
}
function reducer(s,a){
  switch(a.type){
    case 'ADD_EXPENSE':return{...s,expenses:[{...a.payload,id:generateId()},...s.expenses]};
    case 'UPDATE_EXPENSE':return{...s,expenses:s.expenses.map(e=>e.id===a.payload.id?a.payload:e)};
    case 'DELETE_EXPENSE':return{...s,expenses:s.expenses.filter(e=>e.id!==a.payload)};
    case 'ADD_BUDGET':return{...s,budgets:[...s.budgets,{...a.payload,id:generateId()}]};
    case 'UPDATE_BUDGET':return{...s,budgets:s.budgets.map(b=>b.id===a.payload.id?a.payload:b)};
    case 'DELETE_BUDGET':return{...s,budgets:s.budgets.filter(b=>b.id!==a.payload)};
    case 'ADD_RECURRING':return{...s,recurring:[...s.recurring,{...a.payload,id:generateId()}]};
    case 'UPDATE_RECURRING':return{...s,recurring:s.recurring.map(r=>r.id===a.payload.id?a.payload:r)};
    case 'DELETE_RECURRING':return{...s,recurring:s.recurring.filter(r=>r.id!==a.payload)};
    case 'ADD_BILL':return{...s,bills:[...s.bills,{...a.payload,id:generateId()}]};
    case 'UPDATE_BILL':return{...s,bills:s.bills.map(b=>b.id===a.payload.id?a.payload:b)};
    case 'DELETE_BILL':return{...s,bills:s.bills.filter(b=>b.id!==a.payload)};
    case 'UPDATE_USER':return{...s,user:{...s.user,...a.payload}};
    case 'TOGGLE_DARK':return{...s,darkMode:!s.darkMode};
    case 'SET_NOTIFICATIONS':return{...s,notifications:{...s.notifications,...a.payload}};
    case 'SET_LANGUAGE':return{...s,language:a.payload==='am'?'am':'en'};
    default:return s;
  }
}
function getSpent(cat,month){return S.expenses.filter(e=>e.category===cat&&e.date.startsWith(month)).reduce((a,e)=>a+Number(e.amount),0)}
function getBWS(month){
  const m=month||currentMonthStr();
  return S.budgets.filter(b=>b.month===m).map(b=>{
    const spent=getSpent(b.category,b.month);const pct=b.limit>0?Math.round((spent/b.limit)*100):0;
    return{...b,spent,pct,isOver:spent>b.limit,isWarning:pct>=80&&spent<=b.limit};
  });
}
function getBillAlertCount(){
  const d=new Date();d.setDate(d.getDate()+3);const alert=fmtDate(d);
  return S.bills.filter(b=>b.status==='overdue'||(b.status==='upcoming'&&b.dueDate<=alert)).length;
}
function getBudgetAlerts(){return getBWS(currentMonthStr()).filter(b=>b.pct>=80)}
if(S.darkMode)document.documentElement.classList.add('dark');

// â•â•â•â•â•â• ROUTER â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
let PAGE='dashboard';
let profileTab='account';
let sidebarCollapsed=false;
function navigate(page){PAGE=page;if(page!=='profile')profileTab='account';renderLayout();renderPage()}
function closeExpModal(){document.getElementById('expense-modal-root').innerHTML='';renderPage()}

// â•â•â•â•â•â• NAV CONFIG â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
const NAV=[
  {page:'dashboard',icon:'layout-dashboard',label:'Dashboard'},
  {page:'expenses',icon:'receipt',label:'Expenses'},
  {page:'budgets',icon:'target',label:'Budgets'},
  {page:'recurring',icon:'refresh-cw',label:'Recurring'},
  {page:'bills',icon:'zap',label:'Bills'},
  {page:'reports',icon:'bar-chart-2',label:'Reports'},
  {page:'profile',icon:'user',label:'Profile'},
];

// â•â•â•â•â•â• LAYOUT â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function renderLayout(){
  const root=document.getElementById('app-root');
  if(!document.getElementById('topbar-inner')){
    root.innerHTML=`
    <header id="topbar"><div id="topbar-inner" style="display:contents"></div></header>
    <aside id="sidebar"></aside>
    <div id="mobile-overlay"><div id="mobile-backdrop"></div><aside id="mobile-drawer"></aside></div>
    <main id="content"><div id="page" class="anim-fade"></div></main>
    <nav id="bottom-nav"></nav>`;
    document.getElementById('mobile-backdrop').addEventListener('click',closeMobileMenu);
  }
  renderTopbar();renderSidebar();renderMobileDrawer();renderBottomNav();
}
function closeMobileMenu(){document.getElementById('mobile-overlay').classList.remove('open')}
function openMobileMenu(){document.getElementById('mobile-overlay').classList.add('open');renderMobileDrawer()}

function renderTopbar(){
  const tb=document.getElementById('topbar-inner');if(!tb)return;tb.innerHTML='';
  const s=S;const user=Auth.session();
  const displayName=user?.name||s.user.name;const avatarImg=s.user.avatar||user?.avatar;
  // Mobile menu btn
  const menuBtn=el('button',{cls:'icon-btn',style:{display:'none'},id:'menu-btn'});menuBtn.appendChild(ic('menu',20));menuBtn.addEventListener('click',openMobileMenu);
  // Logo
  const logo=appBrand({height:'2rem',compact:true});
  // Search
  const srch=el('div',{style:{flex:1,maxWidth:'28rem',margin:'0 auto'}});
  const srchWrap=el('div',{style:{position:'relative',display:'block'}});
  const sIco=ic('search',16);sIco.style.cssText='position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;z-index:1';
  const sIn=el('input',{cls:'input',style:{paddingLeft:'2.25rem',paddingRight:'.75rem',paddingTop:'.375rem',paddingBottom:'.375rem',fontSize:'.875rem'},placeholder:'Search expenses...'});
  sIn.addEventListener('keydown',e=>{if(e.key==='Enter'&&sIn.value.trim()){sessionStorage.setItem('srch_q',sIn.value.trim());navigate('expenses')}});
  srchWrap.append(sIco,sIn);srch.appendChild(srchWrap);
  // Right
  const right=el('div',{cls:'flex items-center gap-2',style:{marginLeft:'auto'}});
  // Bell
  const recurDue=S.recurring.filter(r=>r.active&&r.nextDue<=todayStr());
  const alertCount=getBillAlertCount()+getBudgetAlerts().length+recurDue.length;
  const bellWrap=el('div',{cls:'relative'});
  const bellBtn=el('button',{cls:'icon-btn',style:{position:'relative'}});bellBtn.appendChild(ic('bell',18));
  if(alertCount>0){const bdg=el('span',{cls:'notif-badge'},alertCount>9?'9+':String(alertCount));bellBtn.appendChild(bdg)}
  let notifOpen=false;
  bellBtn.addEventListener('click',e=>{e.stopPropagation();notifOpen=!notifOpen;renderNotifDrop()});
  bellWrap.appendChild(bellBtn);
  function renderNotifDrop(){
    bellWrap.querySelector('.ndrop')?.remove();if(!notifOpen)return;
    const drop=el('div',{cls:'ndrop',style:{position:'absolute',right:0,top:'100%',marginTop:'.5rem',width:'18rem',background:s.darkMode?'#1e293b':'#fff',borderRadius:'1rem',boxShadow:'0 20px 25px -5px rgba(0,0,0,.15)',border:'1px solid '+(s.darkMode?'#334155':'#f1f5f9'),padding:'.75rem',zIndex:50}});
    drop.appendChild(el('p',{cls:'label mb-2 px-1'},'Notifications'));
    const budgAlerts=getBudgetAlerts();
    budgAlerts.forEach(a=>{
      const row=el('div',{cls:'flex items-start gap-2 p-2 rounded-xl',style:{cursor:'pointer',transition:'background .15s'}});
      row.addEventListener('mouseenter',()=>{row.style.background=s.darkMode?'#334155':'#f8fafc'});
      row.addEventListener('mouseleave',()=>{row.style.background=''});
      row.addEventListener('click',()=>{navigate('budgets');notifOpen=false;renderNotifDrop()});
      const warnIco=ic('alert-triangle',16);warnIco.style.color='#f97316';
      row.append(warnIco,el('div',{},[el('p',{cls:'text-sm font-medium'},a.category+' budget at '+a.pct+'%'),el('p',{cls:'text-xs text-slate-400'},formatETB(a.spent)+' of '+formatETB(a.limit))]));
      drop.appendChild(row);
    });
    const ba=getBillAlertCount();
    if(ba>0){
      const row=el('div',{cls:'flex items-start gap-2 p-2 rounded-xl',style:{cursor:'pointer',transition:'background .15s'}});
      row.addEventListener('mouseenter',()=>{row.style.background=s.darkMode?'#334155':'#f8fafc'});
      row.addEventListener('mouseleave',()=>{row.style.background=''});
      row.addEventListener('click',()=>{navigate('bills');notifOpen=false;renderNotifDrop()});
      const billIco=ic('zap',16);billIco.style.color='#ef4444';
      row.append(billIco,el('div',{},[el('p',{cls:'text-sm font-medium'},billAttentionLabel(ba)),el('p',{cls:'text-xs text-slate-400'},'Overdue or due within 3 days')]));
      drop.appendChild(row);
    }
    if(recurDue.length>0){
      const row=el('div',{cls:'flex items-start gap-2 p-2 rounded-xl',style:{cursor:'pointer',transition:'background .15s'}});
      row.addEventListener('mouseenter',()=>{row.style.background=s.darkMode?'#334155':'#f8fafc'});
      row.addEventListener('mouseleave',()=>{row.style.background=''});
      row.addEventListener('click',()=>{navigate('recurring');notifOpen=false;renderNotifDrop()});
      const rIco=ic('refresh-cw',16);rIco.style.color='#ea580c';
      row.append(rIco,el('div',{},[el('p',{cls:'text-sm font-medium'},recurDue.length+' recurring item'+(recurDue.length>1?'s':'')+' due'),el('p',{cls:'text-xs text-slate-400'},recurDue.map(r=>r.name).slice(0,2).join(', ')+(recurDue.length>2?'...':''))]));
      drop.appendChild(row);
    }
    if(alertCount===0)drop.appendChild(el('p',{cls:'text-sm text-center py-3',style:{color:'#94a3b8'}},'All clear!'));
    bellWrap.appendChild(drop);
    const close=()=>{notifOpen=false;renderNotifDrop();document.removeEventListener('click',close)};
    setTimeout(()=>document.addEventListener('click',close),10);
  }
  right.appendChild(bellWrap);
  // Avatar
  const avBtn=el('button',{style:{width:'2rem',height:'2rem',borderRadius:'9999px',background:s.darkMode?'rgba(13,148,136,.3)':'#ccfbf1',display:'flex',alignItems:'center',justifyContent:'center',overflow:'hidden',border:'none',cursor:'pointer',flexShrink:0}});
  if(avatarImg){avBtn.appendChild(el('img',{src:avatarImg,alt:'Avatar',style:{width:'100%',height:'100%',objectFit:'cover'}}))}
  else avBtn.appendChild(el('span',{style:{color:'#0f766e',fontSize:'.875rem',fontWeight:'700'}},displayName.charAt(0).toUpperCase()));
  avBtn.addEventListener('click',()=>navigate('profile'));right.appendChild(avBtn);
  tb.append(menuBtn,logo,srch,right);
  // Mobile: show menu, hide search on mobile via media query class
  const mStyle=document.createElement('style');mStyle.textContent='@media(max-width:767px){#menu-btn{display:flex!important}#topbar-inner>div:nth-child(3){display:none!important}}';
  tb.appendChild(mStyle);renderIcons(document.getElementById('topbar'));
}

function renderSidebar(){
  const sb=document.getElementById('sidebar');if(!sb)return;sb.innerHTML='';
  if(sidebarCollapsed){sb.classList.add('collapsed');document.getElementById('content').classList.add('collapsed')}
  else{sb.classList.remove('collapsed');document.getElementById('content').classList.remove('collapsed')}
  const sideHdr=el('div',{cls:'sidebar-toggle-row'});
  const menuToggle=el('button',{cls:'icon-btn sidebar-toggle-btn',type:'button',title:sidebarCollapsed?t('Expand sidebar'):t('Collapse sidebar')});
  const panelIco=ic(sidebarCollapsed?'panel-left-open':'panel-left',20);
  panelIco.style.color=S.darkMode?'#cbd5e1':'#475569';
  menuToggle.appendChild(panelIco);
  menuToggle.addEventListener('click',()=>{sidebarCollapsed=!sidebarCollapsed;renderSidebar()});
  sideHdr.appendChild(menuToggle);sb.appendChild(sideHdr);
  const addBtn=el('div',{style:{padding:'.75rem'}});
  const btn=el('button',{cls:'btn-primary w-full',style:{justifyContent:'center'}});
  btn.append(ic('plus-circle',16),sidebarCollapsed?'':' Add Expense');
  btn.addEventListener('click',()=>renderExpModal(null));addBtn.appendChild(btn);sb.appendChild(addBtn);
  const nav=el('nav',{style:{flex:1,padding:'0 .5rem',overflowY:'auto'}});
  NAV.forEach(({page,icon,label})=>{
    const item=el('button',{cls:'nav-item'+(PAGE===page?' active':'')});
    const ico=ic(icon,18);ico.style.flexShrink='0';item.appendChild(ico);
    if(!sidebarCollapsed){
      item.appendChild(el('span',{cls:'nav-label'},label));
      if(label==='Bills'){const ba=getBillAlertCount();if(ba>0){const bdg=el('span',{style:{marginLeft:'auto',background:'#ef4444',color:'#fff',fontSize:'10px',fontWeight:'700',width:'1.25rem',height:'1.25rem',borderRadius:'9999px',display:'flex',alignItems:'center',justifyContent:'center'}},String(ba));item.appendChild(bdg)}}
    }
    item.addEventListener('click',()=>navigate(page));nav.appendChild(item);
  });
  sb.appendChild(nav);
  const bottom=el('div',{style:{padding:'.5rem',borderTop:'1px solid '+(S.darkMode?'#1e293b':'#f1f5f9')}});
  const logBtn=el('button',{cls:'nav-item w-full',style:{color:'#94a3b8'}});
  logBtn.append(ic('log-out',16),sidebarCollapsed?'':' Logout');
  logBtn.addEventListener('click',async()=>{await Auth.logout();renderApp()});bottom.appendChild(logBtn);
  sb.appendChild(bottom);renderIcons(sb);
}

function renderMobileDrawer(){
  const d=document.getElementById('mobile-drawer');if(!d)return;d.innerHTML='';
  const mLogo=el('div',{cls:'mobile-drawer-brand'});
  mLogo.appendChild(appBrand({height:'2.5rem'}));
  d.appendChild(mLogo);
  const addBtn=el('button',{cls:'btn-primary',style:{margin:'.75rem',width:'calc(100% - 1.5rem)',justifyContent:'center'}});
  addBtn.append(ic('plus-circle',16),' Add Expense');
  addBtn.addEventListener('click',()=>{closeMobileMenu();renderExpModal(null)});d.appendChild(addBtn);
  const nav=el('nav',{style:{flex:1,padding:'0 .5rem',overflowY:'auto'}});
  NAV.forEach(({page,icon,label})=>{
    const item=el('button',{cls:'nav-item'+(PAGE===page?' active':'')});
    item.append(ic(icon,18),' '+label);
    item.addEventListener('click',()=>{navigate(page);closeMobileMenu()});nav.appendChild(item);
  });
  d.appendChild(nav);
  const logBtn=el('button',{cls:'nav-item',style:{margin:'.5rem',color:'#94a3b8'}});
  logBtn.append(ic('log-out',16),' Logout');
  logBtn.addEventListener('click',async()=>{await Auth.logout();renderApp()});d.appendChild(logBtn);
  renderIcons(d);
}

function renderBottomNav(){
  const bn=document.getElementById('bottom-nav');if(!bn)return;bn.innerHTML='';
  const mNav=[{page:'dashboard',icon:'layout-dashboard',label:'Home'},{page:'expenses',icon:'receipt',label:'Expenses'},{page:'bills',icon:'zap',label:'Bills'},{page:'profile',icon:'user',label:'Profile'}];
  const left=mNav.slice(0,2),right=mNav.slice(2);
  function navItem({page,icon,label}){
    const item=el('button',{style:{flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:'.1rem',fontSize:'.625rem',fontWeight:'500',cursor:'pointer',color:PAGE===page?'#0d9488':'#94a3b8',transition:'color .15s',border:'none',background:'none',padding:'.5rem 0'}});
    item.append(ic(icon,20),el('span',{},label));item.addEventListener('click',()=>navigate(page));return item;
  }
  left.forEach(n=>bn.appendChild(navItem(n)));
  const center=el('div',{style:{flex:1,display:'flex',justifyContent:'center',alignItems:'center'}});
  const addBtn=el('button',{style:{width:'3rem',height:'3rem',background:'#0d9488',borderRadius:'9999px',display:'flex',alignItems:'center',justifyContent:'center',boxShadow:'0 4px 6px -1px rgba(13,148,136,.4)',border:'3px solid '+(S.darkMode?'#0f172a':'#fff'),marginTop:'-1.25rem',cursor:'pointer',color:'#fff'}});
  addBtn.appendChild(ic('plus',22));addBtn.addEventListener('click',()=>renderExpModal(null));
  center.appendChild(addBtn);bn.appendChild(center);
  right.forEach(n=>bn.appendChild(navItem(n)));
  renderIcons(bn);
}

// â•â•â•â•â•â• EXPENSE MODAL â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function renderExpModal(editExp){
  const container=document.getElementById('expense-modal-root');container.innerHTML='';
  const s=S;
  let form={amount:editExp?String(editExp.amount):'',category:editExp?.category||'Food',date:editExp?.date||todayStr(),note:editExp?.note||'',receipt:editExp?.receipt||null,isRecurring:false,recFreq:'monthly',recName:''};
  let errs={};
  function close(){container.innerHTML=''}
  function submit(){
    errs={};
    if(!isValidAmount(form.amount))errs.amount='Enter a valid amount';
    if(!isValidDate(form.date))errs.date='Date required';
    if(form.isRecurring&&!editExp&&!form.recName.trim())errs.recName='Name required';
    if(Object.keys(errs).length){render();return}
    const amt=Number(form.amount);
    if(editExp)dispatch({type:'UPDATE_EXPENSE',payload:{...editExp,amount:amt,category:form.category,date:form.date,note:form.note,receipt:form.receipt}});
    else{
      dispatch({type:'ADD_EXPENSE',payload:{amount:amt,category:form.category,date:form.date,note:form.note,receipt:form.receipt}});
      if(form.isRecurring)dispatch({type:'ADD_RECURRING',payload:{name:form.recName.trim()||form.note||form.category,amount:amt,category:form.category,frequency:form.recFreq,startDate:form.date,endDate:null,nextDue:advanceByFrequency(form.date,form.recFreq),active:true}});
    }
    close();renderPage();
  }
  function render(){
    const s=getState();
    container.innerHTML='';
    const bd=el('div',{cls:'modal-backdrop center'});
    const box=el('div',{cls:'modal-box'});
    // Header
    const hdr=el('div',{cls:'modal-header'});
    hdr.append(el('h2',{cls:'font-bold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},editExp?'Edit Expense':'Add Expense'));
    const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',close);hdr.appendChild(xBtn);
    box.appendChild(hdr);
    const body=el('div',{style:{padding:'1.25rem'},cls:'space-y-4'});
    // Amount
    const amtWrap=el('div');amtWrap.appendChild(el('label',{cls:'label'},'Amount *'));
    const amtRow=el('div',{cls:'relative'});
    const etbLbl=el('span',{style:{position:'absolute',left:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#94a3b8',fontFamily:'monospace',fontSize:'.8rem',fontWeight:'600'}});etbLbl.textContent='ETB';
    const amtIn=el('input',{type:'number',step:'1',min:'0.01',placeholder:'0',cls:'input'+(errs.amount?' error':''),style:{paddingLeft:'3rem',fontSize:'1.25rem',fontWeight:'700',fontFamily:'monospace'}});
    amtIn.value=form.amount;amtIn.addEventListener('input',e=>{form.amount=e.target.value;errs.amount=''});
    amtRow.append(etbLbl,amtIn);amtWrap.appendChild(amtRow);
    if(errs.amount)amtWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.amount));
    body.appendChild(amtWrap);
    // Cat + Date
    const row1=el('div',{cls:'grid g2 gap-3'});
    const catWrap=buildCategoryField(s.categories,form.category,v=>{form.category=v});
    row1.appendChild(catWrap);
    const dateWrap=el('div');dateWrap.appendChild(el('label',{cls:'label'},'Date *'));
    const dateIn=el('input',{type:'date',cls:'input'+(errs.date?' error':'')});dateIn.value=form.date;
    dateIn.addEventListener('change',e=>{form.date=e.target.value;errs.date=''});dateWrap.appendChild(dateIn);
    if(errs.date)dateWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.date));
    row1.appendChild(dateWrap);body.appendChild(row1);
    // Note
    const noteWrap=el('div');noteWrap.appendChild(el('label',{cls:'label'},'Note (optional)'));
    const noteIn=el('input',{cls:'input',placeholder:'What was this for?'});noteIn.value=form.note;
    noteIn.addEventListener('input',e=>{form.note=e.target.value});noteWrap.appendChild(noteIn);body.appendChild(noteWrap);
    // Receipt
    const recWrap=el('div');recWrap.appendChild(el('label',{cls:'label'},'Receipt (optional)'));
    const dropZone=el('div',{style:{border:'2px dashed #e2e8f0',borderRadius:'.75rem',padding:'1rem',textAlign:'center',cursor:'pointer',transition:'border-color .15s'}});
    dropZone.addEventListener('mouseenter',()=>{dropZone.style.borderColor='#0d9488'});
    dropZone.addEventListener('mouseleave',()=>{dropZone.style.borderColor='#e2e8f0'});
    if(form.receipt){
      const img=el('img',{src:form.receipt,alt:'Receipt',style:{maxHeight:'7rem',margin:'0 auto',borderRadius:'.5rem',display:'block',objectFit:'cover'}});
      const rmBtn=el('button',{style:{color:'#ef4444',fontSize:'.75rem',marginTop:'.5rem',background:'none',border:'none',cursor:'pointer'}},'Remove');
      rmBtn.addEventListener('click',e=>{e.stopPropagation();form.receipt=null;render()});
      dropZone.append(img,rmBtn);
    }else{
      const uIco=ic('upload',24);uIco.style.cssText='margin:0 auto;display:block;color:#94a3b8';
      dropZone.append(uIco,el('p',{cls:'text-sm mt-1',style:{color:'#64748b'}},'Tap to upload receipt'),el('p',{cls:'text-xs mt-1',style:{color:'#94a3b8'}},'PNG / JPG up to 5 MB'));
    }
    const fileIn=el('input',{type:'file',accept:'image/*',cls:'hidden'});
    fileIn.addEventListener('change',e=>{const f=e.target.files[0];if(!f)return;if(f.size>5*1024*1024){alert('File must be under 5 MB');return}const r=new FileReader();r.onload=ev=>{form.receipt=ev.target.result;render()};r.readAsDataURL(f)});
    dropZone.addEventListener('click',()=>fileIn.click());
    recWrap.append(dropZone,fileIn);body.appendChild(recWrap);
    // Recurring toggle (add only)
    if(!editExp){
      const recCard=el('div',{style:{background:s.darkMode?'rgba(51,65,85,.4)':'#f8fafc',borderRadius:'.75rem',padding:'.75rem'}});
      const recRow=el('div',{cls:'flex items-center gap-3'});
      const recIco=ic('refresh-cw',16);recIco.style.color='#94a3b8';
      const recInfo=el('div',{style:{flex:1}});
      recInfo.append(el('p',{cls:'text-sm font-medium',style:{color:s.darkMode?'#cbd5e1':'#334155'}},'Make recurring'),el('p',{cls:'text-xs',style:{color:'#94a3b8'}},'Add to recurring expenses'));
      const tog=Toggle(form.isRecurring,v=>{form.isRecurring=v;render()});
      recRow.append(recIco,recInfo,tog);recCard.appendChild(recRow);
      if(form.isRecurring){
        const extra=el('div',{cls:'space-y-2 mt-3'});
        const nmWrap=el('div');nmWrap.appendChild(el('label',{cls:'label'},'Recurring name *'));
        const nmIn=el('input',{cls:'input'+(errs.recName?' error':''),placeholder:'e.g. Netflix, Rent'});nmIn.value=form.recName;
        nmIn.addEventListener('input',e=>{form.recName=e.target.value;errs.recName=''});nmWrap.appendChild(nmIn);
        if(errs.recName)nmWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.recName));
        const fqWrap=el('div');fqWrap.appendChild(el('label',{cls:'label'},'Frequency'));
        const fqSel=el('select',{cls:'input text-sm'});
        Object.entries(FREQ_LABELS).forEach(([k,v])=>{const o=el('option',{value:k},v);if(k===form.recFreq)o.selected=true;fqSel.appendChild(o)});
        fqSel.addEventListener('change',e=>{form.recFreq=e.target.value});fqWrap.appendChild(fqSel);
        extra.append(nmWrap,fqWrap);recCard.appendChild(extra);
      }
      body.appendChild(recCard);
    }
    box.appendChild(body);
    const footer=el('div',{cls:'modal-footer'});
    const cancelBtn=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancelBtn.addEventListener('click',close);
    const saveBtn=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},editExp?'Save Changes':'Add Expense');saveBtn.addEventListener('click',submit);
    footer.append(cancelBtn,saveBtn);box.appendChild(footer);
    bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd)close()});
    container.appendChild(bd);renderIcons(container);
    if(!editExp)setTimeout(()=>amtIn.focus(),50);
  }
  render();
}

// â•â•â•â•â•â• LOGIN PAGE â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function renderLogin(){
  const root=document.getElementById('app-root');root.innerHTML='';
  const page=el('div',{cls:'auth-page'});
  const wrap=el('div',{style:{width:'100%',maxWidth:'26rem'}});
  // Logo header
  const logoWrap=el('div',{cls:'auth-logo-wrap'});
  logoWrap.appendChild(appLogo('7.5rem',{margin:'0 auto',borderRadius:'1rem',boxShadow:'0 12px 28px rgba(0,0,0,.25)'}));
  const card=el('div',{style:{background:'#1e293b',borderRadius:'1.25rem',padding:'1.5rem',border:'1px solid rgba(51,65,85,.5)',boxShadow:'0 25px 50px -12px rgba(0,0,0,.5)'}});
  let tab='signin',showPw=false,loading=false,errMsg='';
  let googleRenderId=0;
  let form={name:'',email:'',password:'',confirm:''};
  // Forgot state
  let forgot=false,fStep='send',fEmail='',fOtp='',fOtpInput='',fNewPw='',fConfPw='',fErr='',fTimer=0,fTint=null;
  let forgotTimerButtons=[];
  function stopForgotTimer(){if(fTint){clearInterval(fTint);fTint=null}}
  function syncForgotTimerButtons(){
    forgotTimerButtons=forgotTimerButtons.filter(btn=>btn&&btn.isConnected);
    forgotTimerButtons.forEach(btn=>{
      btn.disabled=fTimer>0;
      btn.textContent=fTimer>0?'Resend in '+fTimer+'s':'Resend OTP';
      btn.style.color=fTimer>0?'#475569':'#0d9488';
      btn.style.cursor=fTimer>0?'not-allowed':'pointer';
    });
  }
  function startForgotTimer(){
    stopForgotTimer();
    syncForgotTimerButtons();
    if(fTimer<=0)return;
    fTint=setInterval(()=>{
      fTimer=Math.max(0,fTimer-1);
      syncForgotTimerButtons();
      if(fTimer<=0)stopForgotTimer();
    },1000);
  }
  // Google slot is created ONCE outside render() so it never re-renders and causes layout snap
  const divRow=el('div',{cls:'flex items-center gap-3',style:{margin:'1.25rem 0'}});
  divRow.append(el('div',{style:{flex:1,height:'1px',background:'#334155'}}),el('span',{style:{color:'#64748b',fontSize:'.75rem'}},'or'),el('div',{style:{flex:1,height:'1px',background:'#334155'}}));
  const gSlot=el('div',{style:{display:'flex',justifyContent:'center',minHeight:'44px'}});
  // Initialise Google button immediately (one-time)
  (()=>{
    gSlot.appendChild(el('div',{style:{color:'#64748b',fontSize:'.75rem',padding:'.75rem 0'}},'Loading Google Sign-In...'));
    setGoogleCredentialHandler(async response=>{
      if(!response?.credential){errMsg='Google did not return a valid credential.';render();return}
      loading=true;errMsg='';render();
      try{await Auth.googleLogin(response.credential);renderApp();}
      catch(e){loading=false;errMsg=e.message;render();}
    });
    setTimeout(()=>{
      renderGoogleIdentityButton(gSlot).catch(err=>{
        gSlot.innerHTML='';
        gSlot.appendChild(el('div',{style:{width:'100%',background:'#0f172a',border:'1px solid #334155',color:'#fca5a5',padding:'.75rem',borderRadius:'.75rem',fontSize:'.875rem',textAlign:'center'}},err.message));
      });
    },0);
  })();
  function render(){card.innerHTML='';if(forgot){renderForgot();return}stopForgotTimer();
    // Navigation header
    if(tab==='signin'){
      // Login page: "Create Account" link is at the bottom
    }else{
      // Signup page: simple "Back to Sign In" link, no tab bar
      const backRow=el('div',{style:{marginBottom:'1.25rem'}});
      const backBtn=el('button',{style:{color:'#0d9488',fontSize:'.875rem',border:'none',background:'none',cursor:'pointer',display:'flex',alignItems:'center',gap:'.35rem'}});
      backBtn.append(ic('arrow-left',14),' Back to Sign In');
      backBtn.style.color='#0d9488';
      backBtn.addEventListener('click',()=>{tab='signin';errMsg='';form={name:'',email:'',password:'',confirm:''};render()});
      backRow.appendChild(backBtn);
      card.appendChild(backRow);
    }
    if(errMsg){card.appendChild(el('div',{style:{background:'rgba(239,68,68,.15)',border:'1px solid rgba(239,68,68,.3)',borderRadius:'.75rem',padding:'.75rem',fontSize:'.875rem',color:'#fca5a5',marginBottom:'1rem'}},errMsg))}
    const fields=el('div',{cls:'space-y-3'});
    if(tab==='signup'){
      fields.appendChild(mkAuthField('text','Full Name',form.name,v=>form.name=v));
    }
    fields.appendChild(mkAuthField('email','Email Address',form.email,v=>form.email=v,'you@email.com'));
    // Password row with toggle
    const pwWrap=el('div');pwWrap.appendChild(el('label',{style:{display:'block',fontSize:'.75rem',color:'#94a3b8',marginBottom:'.25rem',fontWeight:'500'}},'Password'));
    const pwRow=el('div',{cls:'relative'});
    const pwIn=el('input',{type:showPw?'text':'password',placeholder:'Enter your password',cls:'auth-input pr-10'});
    pwIn.value=form.password;pwIn.addEventListener('input',e=>{form.password=e.target.value;errMsg=''});
    pwIn.addEventListener('keydown',e=>{if(e.key==='Enter')doSubmit()});
    const eyeBtn=el('button',{type:'button',style:{position:'absolute',right:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#64748b',border:'none',background:'none',cursor:'pointer'}});
    eyeBtn.appendChild(ic(showPw?'eye-off':'eye',16));eyeBtn.addEventListener('click',()=>{showPw=!showPw;render()});
    pwRow.append(pwIn,eyeBtn);pwWrap.appendChild(pwRow);fields.appendChild(pwWrap);
    if(tab==='signup'){fields.appendChild(mkAuthField(showPw?'text':'password','Confirm Password',form.confirm,v=>form.confirm=v,'Confirm your password'));}
    const submitBtn=el('button',{cls:'auth-btn',style:{marginTop:'1rem'}},loading?'Please wait...':tab==='signin'?'Sign In':'Create Account');
    submitBtn.disabled=loading;submitBtn.addEventListener('click',doSubmit);fields.appendChild(submitBtn);
    if(tab==='signin'){
      const fRow=el('div',{style:{display:'flex',justifyContent:'space-between',alignItems:'center'}});
      const createLink=el('button',{style:{color:'#0d9488',fontSize:'.875rem',border:'none',background:'none',cursor:'pointer',fontWeight:'600'}},'Create Account');
      createLink.addEventListener('click',()=>{tab='signup';errMsg='';form={name:'',email:'',password:'',confirm:''};render()});
      const fBtn=el('button',{style:{color:'#0d9488',fontSize:'.875rem',border:'none',background:'none',cursor:'pointer'}},'Forgot Password?');
      fBtn.addEventListener('click',()=>{forgot=true;fOtp='';fOtpInput='';fErr='';render()});
      fRow.append(createLink,fBtn);fields.appendChild(fRow);
    }
    card.appendChild(fields);
    // Append the stable divider and Google slot (created once, never re-rendered)
    if(!loading){card.appendChild(divRow);card.appendChild(gSlot);}
    else{
      const ldiv=el('div',{cls:'flex items-center gap-3',style:{margin:'1.25rem 0'}});
      ldiv.append(el('div',{style:{flex:1,height:'1px',background:'#334155'}}),el('span',{style:{color:'#64748b',fontSize:'.75rem'}},'or'),el('div',{style:{flex:1,height:'1px',background:'#334155'}}));
      card.appendChild(ldiv);
      card.appendChild(el('div',{style:{width:'100%',background:'#0f172a',border:'1px solid #334155',color:'#94a3b8',fontWeight:'600',padding:'.75rem',borderRadius:'.75rem',fontSize:'.875rem',textAlign:'center'}},'Connecting to Google...'));
    }
    renderIcons(card);
  }
  function mkAuthField(type,label,val,onChange,ph=''){
    const wrap=el('div');wrap.appendChild(el('label',{style:{display:'block',fontSize:'.75rem',color:'#94a3b8',marginBottom:'.25rem',fontWeight:'500'}},label));
    const inp=el('input',{type,placeholder:ph||label,cls:'auth-input'});inp.value=val;
    inp.addEventListener('input',e=>{onChange(e.target.value);errMsg=''});
    inp.addEventListener('keydown',e=>{if(e.key==='Enter')doSubmit()});
    wrap.appendChild(inp);return wrap;
  }
  function renderForgot(){
    forgotTimerButtons=[];
    if(fStep!=='otp')stopForgotTimer();
    card.innerHTML='';
    const back=el('button',{cls:'flex items-center gap-1',style:{color:'#0d9488',border:'none',background:'none',cursor:'pointer',fontSize:'.875rem',marginBottom:'1rem'}});
    back.append(ic('arrow-left',14),' Back to Sign In');back.addEventListener('click',()=>{forgot=false;fStep='send';fOtp='';fOtpInput='';fErr='';render()});card.appendChild(back);
    const titles={send:'Enter your email',otp:'Enter verification code',newpw:'Set new password',done:'Password reset!'};
    card.append(el('h2',{style:{color:'#fff',fontWeight:'700',fontSize:'1.125rem',marginBottom:'.25rem'}},'Reset Password'),el('p',{style:{color:'#64748b',fontSize:'.875rem',marginBottom:'1rem'}},titles[fStep]));
    if(fErr)card.appendChild(el('div',{style:{background:'rgba(239,68,68,.15)',border:'1px solid rgba(239,68,68,.3)',borderRadius:'.75rem',padding:'.75rem',fontSize:'.875rem',color:'#fca5a5',marginBottom:'1rem'}},fErr));
    const body=el('div',{cls:'space-y-3'});
    if(fStep==='send'){
      const inp=el('input',{type:'email',placeholder:'your@email.com',cls:'auth-input'});inp.value=fEmail;inp.addEventListener('input',e=>fEmail=e.target.value);
      const btn=el('button',{cls:'auth-btn'},'Send OTP Code');
      btn.addEventListener('click',async()=>{fErr='';const emailVal=fEmail.trim();if(!emailVal){fErr='Please enter your email address.';renderForgot();return}if(!emailVal.includes('@')){fErr='Invalid email: missing "@" symbol (e.g. you@gmail.com).';renderForgot();return}const atIdx=emailVal.indexOf('@');const domain=emailVal.slice(atIdx+1);if(!domain||!domain.includes('.')||domain.endsWith('.')||domain.startsWith('.')){fErr='Invalid email: domain must include a "." (e.g. you@gmail.com).';renderForgot();return}const emailRe=/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;if(!emailRe.test(emailVal)){fErr='Please enter a valid email address (e.g. you@gmail.com).';renderForgot();return}try{const otpMeta=await Auth.generateOtp(emailVal);fOtp=otpMeta.message||('We sent a 6-digit code to '+(otpMeta.maskedEmail||emailVal)+'.');fStep='otp';fTimer=60;renderForgot();startForgotTimer()}catch(e){fErr=e.message;renderForgot()}});
      body.append(inp,btn);
    }else if(fStep==='otp'){
      if(fOtp){body.appendChild(el('div',{style:{background:'rgba(59,130,246,.15)',border:'1px solid rgba(59,130,246,.3)',borderRadius:'.75rem',padding:'.75rem',fontSize:'.875rem',color:'#bfdbfe'}},fOtp))}
      const otpIn=el('input',{type:'text',inputmode:'numeric',maxlength:'6',placeholder:'6-digit code',cls:'auth-input',style:{textAlign:'center',fontSize:'1.25rem',fontFamily:'monospace',letterSpacing:'.1em'}});
      otpIn.value=fOtpInput;otpIn.addEventListener('input',e=>fOtpInput=e.target.value.replace(/\D/g,''));
      const vBtn=el('button',{cls:'auth-btn'},'Verify Code');vBtn.addEventListener('click',async()=>{fErr='';try{await Auth.verifyOtp(fEmail.trim(),fOtpInput.trim());fStep='newpw';renderForgot()}catch(e){fErr=e.message;renderForgot()}});
      const resBtn=el('button',{style:{width:'100%',background:'none',border:'none',color:fTimer>0?'#475569':'#0d9488',fontSize:'.875rem',cursor:fTimer>0?'not-allowed':'pointer'}},fTimer>0?'Resend in '+fTimer+'s':'Resend OTP');
      forgotTimerButtons.push(resBtn);syncForgotTimerButtons();
      resBtn.addEventListener('click',async()=>{try{const otpMeta=await Auth.generateOtp(fEmail.trim());fOtp=otpMeta.message||('We sent a 6-digit code to '+(otpMeta.maskedEmail||fEmail.trim())+'.');fTimer=60;renderForgot();startForgotTimer()}catch(e){fErr=e.message;renderForgot()}});
      body.append(otpIn,vBtn,resBtn);
    }else if(fStep==='newpw'){
      const pw1=el('input',{type:'password',placeholder:'New password (min 8 chars)',cls:'auth-input'});pw1.value=fNewPw;pw1.addEventListener('input',e=>fNewPw=e.target.value);
      const pw2=el('input',{type:'password',placeholder:'Confirm new password',cls:'auth-input'});pw2.value=fConfPw;pw2.addEventListener('input',e=>fConfPw=e.target.value);
      const btn=el('button',{cls:'auth-btn'},'Set New Password');
      btn.addEventListener('click',async()=>{fErr='';if(fNewPw.length<8){fErr='Minimum 8 characters.';renderForgot();return}if(fNewPw!==fConfPw){fErr='Passwords do not match.';renderForgot();return}try{await Auth.resetPassword(fEmail.trim(),fNewPw);fStep='done';renderForgot()}catch(e){fErr=e.message;renderForgot()}});
      body.append(pw1,pw2,btn);
    }else{
      const doneIco=ic('check-circle',40);doneIco.style.color='#10b981';
      body.append(el('div',{style:{textAlign:'center',padding:'1rem'}},[doneIco,el('p',{style:{color:'#cbd5e1',marginTop:'.5rem'}},'Password updated! You can now sign in.')]));
      const btn=el('button',{cls:'auth-btn'},'Back to Sign In');btn.addEventListener('click',()=>{forgot=false;fStep='send';fOtp='';fOtpInput='';fErr='';render()});body.appendChild(btn);
    }
    card.appendChild(body);renderIcons(card);
  }
  async function doSubmit(){
    errMsg='';loading=true;render();
    try{
      if(tab==='signin'){
        if(!form.email||!form.password){errMsg='Please fill in all fields.';loading=false;render();return}
        await Auth.login(form.email,form.password);
      }else{
        if(!form.name.trim()){errMsg='Please enter your full name.';loading=false;render();return}
        if(!form.email){errMsg='Please enter your email.';loading=false;render();return}
        {const emailRe=/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;if(!emailRe.test(form.email.trim())){errMsg='Please enter a valid email address (e.g. you@gmail.com).';loading=false;render();return}}
        if(form.password.length<8){errMsg='Password must be at least 8 characters.';loading=false;render();return}
        if(form.password!==form.confirm){errMsg='Passwords do not match.';loading=false;render();return}
        await Auth.signup(form.name.trim(),form.email,form.password);
      }
      renderApp();
    }catch(e){errMsg=e.message;loading=false;render()}
  }
  render();
  wrap.append(logoWrap,card);
  page.appendChild(wrap);root.appendChild(page);renderIcons(page);
}

// â•â•â•â•â•â• PAGE RENDERER â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function renderPage(){
  const root=document.getElementById('page');if(!root)return;
  // Restart the fade-in animation on every navigation (re-setting the same
  // class name is a no-op in browsers, so we remove it, force a reflow,
  // then re-add it).
  root.classList.remove('anim-fade');
  root.innerHTML='';
  void root.offsetWidth; // force reflow
  root.classList.add('anim-fade');
  ({dashboard:pgDashboard,expenses:pgExpenses,budgets:pgBudgets,recurring:pgRecurring,bills:pgBills,reports:pgReports,profile:pgProfile}[PAGE]||pgDashboard)(root);
  renderIcons(root);
}

// â•â•â•â•â•â• DASHBOARD â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function pgDashboard(root){
  const s=S,today=todayStr(),month=currentMonthStr(),now=new Date();
  const user=Auth.session();const firstName=(user?.name||s.user.name).split(' ')[0];
  // Compute stats
  const weekStart=new Date(now);weekStart.setDate(now.getDate()-now.getDay());const ws=fmtDate(weekStart);
  const lastM=new Date(now.getFullYear(),now.getMonth()-1,1);const lm=fmtDate(lastM).slice(0,7);
  const todayTotal=s.expenses.filter(e=>e.date===today).reduce((a,e)=>a+e.amount,0);
  const weekTotal=s.expenses.filter(e=>e.date>=ws&&e.date<=today).reduce((a,e)=>a+e.amount,0);
  const monthTotal=s.expenses.filter(e=>e.date.startsWith(month)).reduce((a,e)=>a+e.amount,0);
  const lastMTotal=s.expenses.filter(e=>e.date.startsWith(lm)).reduce((a,e)=>a+e.amount,0);
  const trend=lastMTotal>0?((monthTotal-lastMTotal)/lastMTotal)*100:0;
  // Header
  const hdr=el('div',{cls:'flex items-center justify-between mb-5'});
  const dateLoc=getLang()==='am'?'am-ET':'en';
  const hLeft=el('div');hLeft.append(el('h1',{cls:'section-title'},tGreet()+', '+firstName+'!'),el('p',{cls:'text-sm text-slate-500'},now.toLocaleDateString(dateLoc,{weekday:'long',year:'numeric',month:'long',day:'numeric'})));
  const qBtn=el('button',{cls:'btn-primary hide-mobile'});qBtn.append(ic('plus',16),' Quick Expense');qBtn.addEventListener('click',()=>renderExpModal(null));
  hdr.append(hLeft,qBtn);root.appendChild(hdr);
  // 7-day bar chart card
  const chartCard=el('div',{cls:'card',style:{marginBottom:'1.25rem'}});
  const chHdr=el('div',{cls:'flex items-center justify-between mb-4'});
  const chLeft=el('div');chLeft.append(el('h2',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#0f172a'}},'Spending Overview'),el('p',{cls:'text-xs text-slate-400 mt-1'},'Last 7 days · ETB'));
  if(trend!==0){const tb=el('div',{cls:'flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full'});tb.style.cssText=trend>0?'background:#fef2f2;color:#dc2626':'background:#ecfdf5;color:#059669';tb.append(ic(trend>0?'trending-up':'trending-down',12),' '+Math.abs(trend).toFixed(0)+'% vs last mo');chHdr.appendChild(tb)}
  chHdr.insertBefore(chLeft,chHdr.firstChild);chartCard.appendChild(chHdr);
  const days7=[];for(let i=6;i>=0;i--){const d=new Date();d.setDate(d.getDate()-i);const ds=fmtDate(d);const tot=s.expenses.filter(e=>e.date===ds).reduce((a,e)=>a+e.amount,0);days7.push({label:['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()],date:ds,total:tot})}
  const maxV=Math.max(...days7.map(d=>d.total),1);
  const barChart=el('div',{style:{display:'flex',alignItems:'flex-end',justifyContent:'space-between',gap:'.25rem',height:'7rem',padding:'0 .25rem'}});
  days7.forEach(({label,date,total})=>{
    const isToday=date===today;const pct=(total/maxV)*100;
    const col=el('div',{style:{flex:1,display:'flex',flexDirection:'column',alignItems:'center',gap:'.25rem'}});
    if(total>0)col.appendChild(el('span',{style:{fontSize:'9px',color:'#94a3b8',fontFamily:'monospace'}},total>=1000?(total/1000).toFixed(1)+'k':String(total)));
    const bWrap=el('div',{style:{width:'100%',display:'flex',alignItems:'flex-end',height:'80px'}});
    const bar=el('div',{style:{width:'100%',borderRadius:'4px 4px 0 0',transition:'height .5s',height:Math.max(pct,total>0?6:2)+'%',background:isToday?'#0d9488':s.darkMode?'rgba(13,148,136,.3)':'#ccfbf1'}});
    bWrap.appendChild(bar);col.append(bWrap,el('span',{style:{fontSize:'10px',fontWeight:'500',color:isToday?'#0d9488':'#94a3b8'}},label));barChart.appendChild(col);
  });
  chartCard.appendChild(barChart);
  const statsRow=el('div',{cls:'grid g3 gap-2',style:{marginTop:'1rem'}});
  [['Today',todayTotal],['This Week',weekTotal],['This Month',monthTotal]].forEach(([lbl,val])=>{
    const cell=el('div',{style:{background:s.darkMode?'rgba(51,65,85,.5)':'#f8fafc',borderRadius:'.75rem',padding:'.75rem',textAlign:'center'}});
    cell.append(el('p',{style:{fontSize:'9px',fontWeight:'600',color:'#94a3b8',textTransform:'uppercase',letterSpacing:'.05em',marginBottom:'.25rem'}},lbl),el('p',{cls:'font-bold font-mono text-sm',style:{color:s.darkMode?'#fff':'#1e293b'}},formatCompact(val)));
    statsRow.appendChild(cell);
  });
  chartCard.appendChild(statsRow);root.appendChild(chartCard);
  // 2-col grid — equal gap, matching card header style across Budget Status & Upcoming Bills
  const dashCardHdrStyle='flex items-center justify-between mb-3';
  const dashCardHdrTitleStyle={fontSize:'1rem',fontWeight:'600',color:s.darkMode?'#fff':'#0f172a'};
  const dashSeeAllStyle={color:'#0d9488',fontSize:'.75rem',fontWeight:'500',border:'none',background:'none',cursor:'pointer',display:'flex',alignItems:'center',gap:'.25rem'};
  const grid=el('div',{style:{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(280px,1fr))',gap:'1.25rem',marginBottom:'1.25rem'}});
  // Budget status
  const budgCard=el('div',{cls:'card'});
  const bHdr=el('div',{cls:dashCardHdrStyle});
  bHdr.appendChild(el('h2',{style:dashCardHdrTitleStyle},'Budget Status'));
  const bAll=el('button',{style:dashSeeAllStyle});bAll.append('See all ',ic('arrow-right',12));bAll.addEventListener('click',()=>navigate('budgets'));bHdr.appendChild(bAll);budgCard.appendChild(bHdr);
  const bws=getBWS(month);
  if(!bws.length){const e=el('div',{style:{textAlign:'center',padding:'1.5rem'}});e.append(el('p',{cls:'text-sm text-slate-400'},'No budgets set.'));const b=el('button',{cls:'btn-primary mt-3',style:{margin:'.75rem auto 0',display:'flex'}});b.append(ic('plus',14),' Set Budgets');b.addEventListener('click',()=>navigate('budgets'));e.appendChild(b);budgCard.appendChild(e)}
  else{const l=el('div',{cls:'space-y-3'});bws.forEach(b=>{const row=el('div',{style:{cursor:'pointer'}});row.addEventListener('click',()=>navigate('budgets'));const rHdr=el('div',{cls:'flex items-center justify-between mb-1'});const rL=el('div',{cls:'flex items-center gap-2'});const ico=ic(b.isOver||b.isWarning?'alert-triangle':'check-circle',14);ico.style.color=b.isOver?'#ef4444':b.isWarning?'#f97316':'#10b981';rL.append(ico,el('span',{cls:'text-sm font-semibold',style:{color:s.darkMode?'#fff':'#1e293b'}},tCat(b.category)));const rR=el('span',{cls:'text-xs font-mono font-semibold',style:{color:b.isOver?'#dc2626':b.isWarning?'#ea580c':'#64748b'}},formatETB(b.spent)+'/'+b.limit+' ('+b.pct+'%)');rHdr.append(rL,rR);row.append(rHdr,ProgressBar(b.pct,b.isOver,b.isWarning));l.appendChild(row)});budgCard.appendChild(l)}
  grid.appendChild(budgCard);
  // Upcoming bills
  const billCard=el('div',{cls:'card'});
  const billHdr=el('div',{cls:dashCardHdrStyle});billHdr.appendChild(el('h2',{style:dashCardHdrTitleStyle},'Upcoming Bills'));
  const billAll=el('button',{style:dashSeeAllStyle});billAll.append('See all ',ic('arrow-right',12));billAll.addEventListener('click',()=>navigate('bills'));
  billHdr.appendChild(billAll);billCard.appendChild(billHdr);
  const upcoming=s.bills.filter(b=>b.status!=='paid').sort((a,b)=>a.dueDate.localeCompare(b.dueDate)).slice(0,3);
  if(!upcoming.length){const e=el('div',{style:{textAlign:'center',padding:'1.5rem'}});e.append(el('p',{cls:'text-sm text-slate-400'},'No upcoming bills'));const b=el('button',{cls:'btn-primary mt-3',style:{margin:'.75rem auto 0',display:'flex'}});b.append(ic('plus',14),' Add Bill');b.addEventListener('click',()=>navigate('bills'));e.appendChild(b);billCard.appendChild(e)}
  else{const l=el('div',{cls:'space-y-2'});upcoming.forEach(bill=>{const isOv=bill.status==='overdue';const row=el('div',{cls:'flex items-center justify-between p-3 rounded-xl',style:{cursor:'pointer',transition:'background .15s'}});row.addEventListener('mouseenter',()=>{row.style.background=s.darkMode?'rgba(51,65,85,.4)':'#f8fafc'});row.addEventListener('mouseleave',()=>{row.style.background=''});row.addEventListener('click',()=>navigate('bills'));const lft=el('div',{cls:'flex items-center gap-3'});const iBox=el('div',{style:{width:'2rem',height:'2rem',borderRadius:'.5rem',display:'flex',alignItems:'center',justifyContent:'center',background:isOv?'#fee2e2':'#f0fdfa'}});const zIco=ic('zap',14);zIco.style.color=isOv?'#ef4444':'#0d9488';iBox.appendChild(zIco);const info=el('div');info.append(el('p',{cls:'text-sm font-medium',style:{color:s.darkMode?'#fff':'#1e293b'}},bill.name),el('p',{cls:'text-xs',style:{color:isOv?'#ef4444':'#64748b',fontWeight:isOv?'600':'400'}},daysUntilInfo(bill.dueDate).label));lft.append(iBox,info);row.append(lft,el('span',{cls:'text-sm font-semibold font-mono',style:{color:s.darkMode?'#cbd5e1':'#334155'}},formatETB(Number(bill.amount))));l.appendChild(row)});billCard.appendChild(l)}
  grid.appendChild(billCard);root.appendChild(grid);
  // Recent expenses — same card header pattern
  const recCard=el('div',{cls:'card',style:{marginBottom:'1.25rem'}});const recHdr=el('div',{cls:dashCardHdrStyle});recHdr.appendChild(el('h2',{style:dashCardHdrTitleStyle},'Recent Expenses'));
  const recAll=el('button',{style:dashSeeAllStyle});recAll.append('View all ',ic('arrow-right',12));recAll.addEventListener('click',()=>navigate('expenses'));recHdr.appendChild(recAll);recCard.appendChild(recHdr);
  const recent=s.expenses.slice(0,5);
  if(!recent.length){recCard.appendChild(el('p',{cls:'text-sm text-slate-400',style:{textAlign:'center',padding:'1.5rem'}},'No expenses yet. Add one!'))}
  else{
    const list=el('div',{style:{borderTop:'1px solid '+(s.darkMode?'rgba(51,65,85,.4)':'#f8fafc')}});
    recent.forEach(exp=>{
      const row=el('div',{cls:'flex items-center gap-3',style:{padding:'.625rem .375rem',borderRadius:'.75rem',cursor:'pointer',transition:'background .15s',margin:'0 -.375rem'}});
      row.addEventListener('mouseenter',()=>{row.style.background=s.darkMode?'rgba(51,65,85,.3)':'#f8fafc'});
      row.addEventListener('mouseleave',()=>{row.style.background=''});
      row.addEventListener('click',()=>navigate('expenses'));
      const catBox=el('div',{cls:'rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0 '+getCatClass(exp.category),style:{width:'2rem',height:'2rem'}},exp.category.charAt(0));
      const info=el('div',{style:{flex:1,minWidth:0}});
      info.append(el('p',{cls:'text-sm font-medium truncate',style:{color:s.darkMode?'#fff':'#1e293b'}},exp.note||tCat(exp.category)),el('p',{cls:'text-xs text-slate-400'},tCat(exp.category)+' · '+(exp.date===today?t('Today'):exp.date)));
      row.append(catBox,info,el('span',{cls:'text-sm font-semibold font-mono flex-shrink-0',style:{color:s.darkMode?'#cbd5e1':'#334155'}},formatETB(exp.amount)));
      list.appendChild(row);
    });
    recCard.appendChild(list);
  }
  root.appendChild(recCard);
}

// â•â•â•â•â•â• EXPENSES â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function pgExpenses(root){
  let q=sessionStorage.getItem('srch_q')||'';sessionStorage.removeItem('srch_q');
  let cat='All',dateFrom='',dateTo='',page=1,selected=[],showFilters=false,delId=null,delBulk=false,totalTimeframe='month',showTotalMenu=false;
  function filtered(s){
    return s.expenses.filter(e=>{
      if(q&&!`${e.note||''} ${e.category} ${e.amount}`.toLowerCase().includes(q.toLowerCase()))return false;
      if(cat!=='All'){
        const isCustomCat=!CATEGORIES.includes(e.category)||e.category==='Other';
        if(cat==='Other'&&!isCustomCat)return false;
        if(cat!=='Other'&&e.category!==cat)return false;
      }
      if(dateFrom&&e.date<dateFrom)return false;
      if(dateTo&&e.date>dateTo)return false;
      return true;
    }).sort((a,b)=>b.date.localeCompare(a.date));
  }
  function render(){
    const s=getState();
    root.innerHTML='';
    const rows=filtered(s);const totalPages=Math.ceil(rows.length/PAGE_SIZE);const paged=rows.slice((page-1)*PAGE_SIZE,page*PAGE_SIZE);
    const totalAmt=rows.reduce((a,e)=>a+e.amount,0);
    // Compute timeframe-based totals
    const nowD=new Date(),curYear=nowD.getFullYear().toString(),curMonth=curYear+'-'+String(nowD.getMonth()+1).padStart(2,'0');
    const timeframeTotal=totalTimeframe==='year'?s.expenses.filter(e=>e.date.startsWith(curYear)).reduce((a,e)=>a+Number(e.amount),0):s.expenses.filter(e=>e.date.startsWith(curMonth)).reduce((a,e)=>a+Number(e.amount),0);
    // Header
    const hdr=el('div',{cls:'flex items-center justify-between mb-4 flex-wrap gap-2'});
    hdr.appendChild(el('h1',{cls:'section-title'},'Expenses'));
    const hRight=el('div',{cls:'flex gap-2'});
    if(selected.length>0){const dBtn=el('button',{cls:'btn-danger'});dBtn.append(ic('trash-2',15),' Delete ('+selected.length+')');dBtn.addEventListener('click',()=>{delBulk=true;render()});hRight.appendChild(dBtn)}
    const csvBtn=el('button',{cls:'btn-secondary'});csvBtn.append(ic('download',15),' CSV');
    csvBtn.addEventListener('click',()=>{const hd='Date,Category,Amount,Note\n';const r=rows.map(e=>`${e.date},${e.category},${e.amount},"${(e.note||'').replace(/"/g,'""')}"`);const a=el('a',{href:'data:text/csv,'+encodeURIComponent(hd+r.join('\n')),download:'expenses.csv'});document.body.appendChild(a);a.click();a.remove()});
    hRight.appendChild(csvBtn);hdr.appendChild(hRight);root.appendChild(hdr);
    // Filter card
    const fCard=el('div',{cls:'card p-3 mb-4'});
    const fRow=el('div',{cls:'flex gap-2 flex-wrap items-center'});
    const sWrap=el('div',{style:{position:'relative',flex:1,minWidth:'160px',display:'block'}});
    const sIco=ic('search',14);sIco.style.cssText='position:absolute;left:.625rem;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;z-index:1';
    const sIn=el('input',{cls:'input',style:{paddingLeft:'2rem',paddingRight:'.75rem',paddingTop:'.375rem',paddingBottom:'.375rem',fontSize:'.875rem'},placeholder:'Search...'});sIn.value=q;
    sIn.addEventListener('input',e=>{q=e.target.value;page=1;render()});sWrap.append(sIco,sIn);fRow.appendChild(sWrap);
    const cSel=el('select',{cls:'input',style:{width:'auto',paddingTop:'.375rem',paddingBottom:'.375rem',fontSize:'.875rem'}});
    cSel.appendChild(el('option',{value:'All'},'All categories'));
    s.categories.forEach(c=>{const o=el('option',{value:c},c);if(c===cat)o.selected=true;cSel.appendChild(o)});
    cSel.addEventListener('change',e=>{cat=e.target.value;page=1;render()});fRow.appendChild(cSel);
    const fBtn=el('button',{cls:'btn-secondary',style:{paddingTop:'.375rem',paddingBottom:'.375rem',fontSize:'.875rem'}});fBtn.append(ic('filter',14),' Filters');fBtn.addEventListener('click',()=>{showFilters=!showFilters;render()});fRow.appendChild(fBtn);
    fCard.appendChild(fRow);
    if(showFilters){
      const adv=el('div',{style:{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(130px,1fr))',gap:'.75rem',marginTop:'.75rem',background:s.darkMode?'#1e293b':'#f8fafc',borderRadius:'.75rem',padding:'.75rem',border:'1px solid '+(s.darkMode?'#334155':'#f1f5f9')}});
      const dfIn=el('input',{type:'date',cls:'input',style:{fontSize:'.875rem'}});dfIn.value=dateFrom;dfIn.addEventListener('change',e=>{dateFrom=e.target.value;page=1;render()});
      const dtIn=el('input',{type:'date',cls:'input',style:{fontSize:'.875rem'}});dtIn.value=dateTo;dtIn.addEventListener('change',e=>{dateTo=e.target.value;page=1;render()});
      const clr=el('button',{cls:'btn-secondary',style:{fontSize:'.75rem',paddingTop:'.375rem',paddingBottom:'.375rem'}},'Clear all');
      clr.addEventListener('click',()=>{q='';cat='All';dateFrom='';dateTo='';page=1;render()});
      adv.append(el('div',{},[el('label',{cls:'label'},'From'),dfIn]),el('div',{},[el('label',{cls:'label'},'To'),dtIn]),el('div',{style:{display:'flex',alignItems:'flex-end'}},clr));
      fCard.appendChild(adv);
    }
    root.appendChild(fCard);
    // Summary row
    const sumRow=el('div',{cls:'flex items-center justify-between text-sm mb-3',style:{color:'#64748b'}});
    sumRow.appendChild(el('span',{},rows.length+' expense'+(rows.length!==1?'s':'')));
    // Dynamic total dropdown
    const totalWrap=el('div',{style:{position:'relative'}});
    const totalBtn=el('button',{style:{display:'flex',alignItems:'center',gap:'.35rem',background:'none',border:'1px solid '+(s.darkMode?'#334155':'#e2e8f0'),borderRadius:'.5rem',padding:'.25rem .6rem',cursor:'pointer',fontFamily:'monospace',fontWeight:'600',fontSize:'.875rem',color:s.darkMode?'#cbd5e1':'#334155'}});
    totalBtn.append('Total ('+( totalTimeframe==='year'?'Year':'Month')+'): '+formatETB(timeframeTotal,2),ic('chevron-down',12));
    totalBtn.addEventListener('click',e=>{e.stopPropagation();showTotalMenu=!showTotalMenu;render()});
    totalWrap.appendChild(totalBtn);
    if(showTotalMenu){
      const menu=el('div',{style:{position:'absolute',right:0,top:'calc(100% + 4px)',background:s.darkMode?'#1e293b':'#fff',border:'1px solid '+(s.darkMode?'#334155':'#e2e8f0'),borderRadius:'.5rem',boxShadow:'0 4px 12px rgba(0,0,0,.15)',zIndex:100,minWidth:'9rem',overflow:'hidden'}});
      ['month','year'].forEach(tf=>{
        const opt=el('button',{style:{display:'block',width:'100%',textAlign:'left',padding:'.5rem .75rem',background:totalTimeframe===tf?(s.darkMode?'#0d9488':'#f0fdfa'):'transparent',color:totalTimeframe===tf?'#0d9488':(s.darkMode?'#cbd5e1':'#334155'),fontWeight:totalTimeframe===tf?'600':'400',border:'none',cursor:'pointer',fontSize:'.875rem'}},tf==='month'?'This Month':'This Year');
        opt.addEventListener('click',e=>{e.stopPropagation();totalTimeframe=tf;showTotalMenu=false;render()});
        menu.appendChild(opt);
      });
      totalWrap.appendChild(menu);
      // Close on outside click
      setTimeout(()=>{const close=()=>{showTotalMenu=false;render();document.removeEventListener('click',close)};document.addEventListener('click',close)},0);
    }
    sumRow.appendChild(totalWrap);
    root.appendChild(sumRow);
    // Expense list
    const list=el('div',{cls:'card p-0 overflow-hidden'});
    if(!paged.length){list.appendChild(el('div',{cls:'empty-state'},[ic('receipt',32),el('p',{cls:'font-medium text-slate-500 mt-2'},'No expenses found')]))}
    else paged.forEach(exp=>{
      const row=el('div',{cls:'flex items-center gap-3',style:{padding:'1rem',borderBottom:'1px solid '+(s.darkMode?'rgba(51,65,85,.4)':'#f8fafc'),transition:'background .15s',cursor:'pointer'}});
      row.addEventListener('mouseenter',()=>{row.style.background=s.darkMode?'rgba(51,65,85,.3)':'#f8fafc'});
      row.addEventListener('mouseleave',()=>{row.style.background=''});
      const catBox=el('div',{cls:'rounded-xl flex items-center justify-center text-xs font-bold flex-shrink-0 '+getCatClass(exp.category),style:{width:'2.25rem',height:'2.25rem'}},exp.category.charAt(0));
      const info=el('div',{style:{flex:1,minWidth:0}});
      info.append(el('p',{cls:'text-sm font-medium truncate',style:{color:s.darkMode?'#fff':'#1e293b'}},exp.note||exp.category),el('p',{cls:'text-xs text-slate-400'},exp.category+' · '+exp.date));
      const rht=el('div',{style:{textAlign:'right',flexShrink:0}});
      rht.appendChild(el('p',{cls:'font-semibold font-mono text-sm',style:{color:s.darkMode?'#fff':'#1e293b'}},formatETB(exp.amount)));
      const acts=el('div',{cls:'flex gap-1 justify-end mt-1'});
      const eBtn=el('button',{cls:'icon-btn',style:{padding:'.2rem'}});eBtn.appendChild(ic('edit-2',12));eBtn.addEventListener('click',e=>{e.stopPropagation();renderExpModal(exp)});
      const dBtn=el('button',{cls:'icon-btn danger',style:{padding:'.2rem'}});dBtn.appendChild(ic('trash-2',12));dBtn.addEventListener('click',e=>{e.stopPropagation();delId=exp.id;render()});
      acts.append(eBtn,dBtn);rht.appendChild(acts);row.append(catBox,info,rht);list.appendChild(row);
    });
    root.appendChild(list);
    // Pagination
    if(totalPages>1){
      const pag=el('div',{cls:'flex items-center justify-center gap-3 mt-4'});
      const prev=el('button',{cls:'btn-secondary'},'← Prev');prev.disabled=page===1;prev.addEventListener('click',()=>{page--;render()});
      const next=el('button',{cls:'btn-secondary'},'Next →');next.disabled=page>=totalPages;next.addEventListener('click',()=>{page++;render()});
      pag.append(prev,el('span',{cls:'text-sm text-slate-400'},'Page '+page+' of '+totalPages),next);root.appendChild(pag);
    }
    // Confirm delete single
    if(delId){
      const bd=el('div',{cls:'modal-backdrop center'});const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'22rem',padding:'1.5rem',borderRadius:'1rem'}});
      box.append(el('h3',{cls:'font-bold text-lg mb-2',style:{color:s.darkMode?'#fff':'#0f172a'}},'Delete expense?'),el('p',{cls:'text-sm mb-5',style:{color:'#64748b'}},'This cannot be undone.'));
      const btns=el('div',{cls:'flex gap-3'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{delId=null;render()});
      const confirm=el('button',{style:{flex:1,justifyContent:'center',display:'flex',alignItems:'center',background:'#dc2626',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer'}},'Delete');
      confirm.addEventListener('click',()=>{dispatch({type:'DELETE_EXPENSE',payload:delId});delId=null});
      btns.append(cancel,confirm);box.appendChild(btns);bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){delId=null;render()}});root.appendChild(bd);
    }
    // Confirm bulk delete
    if(delBulk){
      const bd=el('div',{cls:'modal-backdrop center'});const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'22rem',padding:'1.5rem',borderRadius:'1rem'}});
      box.append(el('h3',{cls:'font-bold text-lg mb-2',style:{color:s.darkMode?'#fff':'#0f172a'}},'Delete '+selected.length+' expenses?'),el('p',{cls:'text-sm mb-5',style:{color:'#64748b'}},'All selected expenses will be permanently deleted.'));
      const btns=el('div',{cls:'flex gap-3'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{delBulk=false;render()});
      const confirm=el('button',{style:{flex:1,justifyContent:'center',display:'flex',alignItems:'center',background:'#dc2626',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer'}},'Delete all');
      confirm.addEventListener('click',()=>{selected.forEach(id=>dispatch({type:'DELETE_EXPENSE',payload:id}));selected=[];delBulk=false});
      btns.append(cancel,confirm);box.appendChild(btns);bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){delBulk=false;render()}});root.appendChild(bd);
    }
    renderIcons(root);
  }
  render();
}

// â•â•â•â•â•â• BUDGETS â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function pgBudgets(root){
  let month=currentMonthStr(),editId=null,editLimit='',delId=null,showAdd=false,newCat=getState().categories[0],newLimit='',addErr='';
  function render(){
    const s=getState();
    const nowMonth=currentMonthStr();
    if(month>nowMonth)month=nowMonth;
    root.innerHTML='';
    const budgets=s.budgets.filter(b=>b.month===month);
    const used=budgets.map(b=>b.category);const avail=s.categories.filter(c=>!used.includes(c));
    const totalBudg=budgets.reduce((a,b)=>a+b.limit,0);const totalSpent=budgets.reduce((a,b)=>a+getSpent(b.category,month),0);
    const isCurrentMonth=month===nowMonth;
    const hdr=el('div',{cls:'flex items-center justify-between mb-4 flex-wrap gap-2'});
    hdr.appendChild(el('h1',{cls:'section-title'},'Budgets'));
    const hRight=el('div',{cls:'flex gap-2'});
    const addBtn=el('button',{cls:'btn-primary'});addBtn.append(ic('plus',15),' Add');addBtn.addEventListener('click',()=>{showAdd=true;newCat=avail[0]||s.categories[0];render()});
    hRight.append(addBtn);hdr.appendChild(hRight);root.appendChild(hdr);
    // Month picker — backward nav only, forward capped at real current month
    const picker=el('div',{cls:'card flex items-center justify-between p-3 mb-4'});
    const prevBtn=el('button',{cls:'icon-btn'});prevBtn.appendChild(ic('chevron-left',18));prevBtn.addEventListener('click',()=>{month=subMonth(month);render()});
    const mInfo=el('div',{style:{textAlign:'center'}});
    const mLabelRow=el('div',{style:{display:'flex',alignItems:'center',gap:'.5rem',justifyContent:'center'}});
    mLabelRow.appendChild(el('p',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#0f172a'}},fmtMonthLabel(month)));
    if(isCurrentMonth){mLabelRow.appendChild(el('span',{cls:'badge badge-green',style:{fontSize:'.65rem'}},'Current'))}
    else{const goBtn=el('button',{style:{fontSize:'.7rem',color:'#0d9488',background:'none',border:'none',cursor:'pointer',textDecoration:'underline'}},'Go to current');goBtn.addEventListener('click',()=>{month=nowMonth;render()});mLabelRow.appendChild(goBtn);}
    mInfo.append(mLabelRow,el('p',{cls:'text-xs text-slate-400'},formatETB(totalSpent)+' spent of '+formatETB(totalBudg)));
    const nextBtn=el('button',{cls:'icon-btn'});nextBtn.appendChild(ic('chevron-right',18));nextBtn.disabled=isCurrentMonth;nextBtn.style.opacity=isCurrentMonth?'0.3':'1';nextBtn.style.cursor=isCurrentMonth?'not-allowed':'pointer';nextBtn.addEventListener('click',()=>{if(!isCurrentMonth){month=addMonth(month);render()}});
    picker.append(prevBtn,mInfo,nextBtn);root.appendChild(picker);
    // Overall bar
    if(totalBudg>0){const ov=el('div',{cls:'card mb-4'});const pct=Math.round((totalSpent/totalBudg)*100);const ovH=el('div',{cls:'flex justify-between mb-2'});ovH.append(el('span',{cls:'text-sm font-semibold',style:{color:s.darkMode?'#cbd5e1':'#334155'}},'Overall Budget'),el('span',{cls:'text-sm font-mono text-slate-400'},pct+'%'));ov.append(ovH,ProgressBar(pct,totalSpent>totalBudg,false));root.appendChild(ov)}
    // Add form
    if(showAdd){
      const bd=el('div',{cls:'modal-backdrop center'});
      const box=el('div',{cls:'modal-box'});
      const mHdr=el('div',{cls:'modal-header'});
      mHdr.append(el('h2',{cls:'font-bold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},'Add Budget — '+fmtMonthLabel(month)));
      const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',()=>{showAdd=false;render()});mHdr.appendChild(xBtn);
      box.appendChild(mHdr);
      const body=el('div',{style:{padding:'1.25rem'},cls:'space-y-4'});
      if(!avail.length){body.appendChild(el('p',{cls:'text-sm text-slate-400'},'All categories have budgets this month.'))}
      else{
        const cWrap=buildCategoryField(avail,newCat,v=>{newCat=v},'Category');
        const lWrap=el('div');lWrap.appendChild(el('label',{cls:'label'},'Monthly Limit *'));
        const amtRow=el('div',{style:{position:'relative',display:'block'}});
        const etbLbl=el('span',{style:{position:'absolute',left:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#94a3b8',fontFamily:'monospace',fontSize:'.8rem',fontWeight:'600'}});etbLbl.textContent='ETB';
        const lIn=el('input',{type:'number',min:'1',cls:'input'+(addErr?' error':''),placeholder:'0',style:{paddingLeft:'3rem',fontSize:'1.1rem',fontWeight:'700',fontFamily:'monospace'}});lIn.value=newLimit;lIn.addEventListener('input',e=>{newLimit=e.target.value;addErr=''});
        amtRow.append(etbLbl,lIn);lWrap.appendChild(amtRow);
        if(addErr)lWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},addErr));
        body.append(cWrap,lWrap);
      }
      box.appendChild(body);
      const footer=el('div',{cls:'modal-footer'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{showAdd=false;render()});
      const save=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Add Budget');
      save.addEventListener('click',()=>{const n=Number(newLimit);if(!newLimit||isNaN(n)||n<=0){addErr='Enter a valid positive limit';render();return}dispatch({type:'ADD_BUDGET',payload:{category:newCat,limit:n,month}});showAdd=false;newLimit='';render()});
      footer.append(cancel,save);box.appendChild(footer);
      bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){showAdd=false;render()}});root.appendChild(bd);renderIcons(root);
      setTimeout(()=>lIn?.focus(),50);
    }
    if(!budgets.length){
      const empty=el('div',{cls:'empty-state card'});empty.append(ic('target',32),el('p',{cls:'font-medium text-slate-500 mt-2'},'No budgets for '+fmtMonthLabel(month)));
      const ab=el('button',{cls:'btn-primary mt-3',style:{margin:'.75rem auto 0',display:'flex'}});ab.append(ic('plus',14),' Add budget');ab.addEventListener('click',()=>{showAdd=true;render()});
      empty.appendChild(ab);root.appendChild(empty);renderIcons(root);return;
    }
    const list=el('div',{cls:'space-y-3'});
    budgets.forEach(b=>{
      const spent=getSpent(b.category,month);const pct=Math.round((spent/b.limit)*100);
      const isOver=spent>b.limit,isWarn=pct>=80&&!isOver;
      const card=el('div',{cls:'card'});
      const cHdr=el('div',{cls:'flex items-center justify-between mb-3'});
      const cL=el('div',{cls:'flex items-center gap-2'});
      const ico=ic(isOver||isWarn?'alert-triangle':'check-circle',15);ico.style.color=isOver?'#ef4444':isWarn?'#f97316':'#10b981';ico.style.flexShrink='0';
      const badge=el('span',{cls:'badge '+(isOver?'badge-red':isWarn?'badge-orange':'badge-green')},isOver?'Over':isWarn?'Warning':'On track');
      cL.append(ico,el('span',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#1e293b'}},tCat(b.category)),badge);
      const cR=el('div',{cls:'flex gap-1'});
      const ed=el('button',{cls:'icon-btn'});ed.appendChild(ic('edit-2',14));ed.addEventListener('click',e=>{e.stopPropagation();editId=b.id;editLimit=String(b.limit);render()});
      const dl=el('button',{cls:'icon-btn danger'});dl.appendChild(ic('trash-2',14));dl.addEventListener('click',e=>{e.stopPropagation();delId=b.id;render()});
      cR.append(ed,dl);
      cHdr.append(cL,cR);card.appendChild(cHdr);
      const row2=el('div',{cls:'flex items-center justify-between text-sm mb-2'});
      const spentSpan=el('span',{style:{color:'#64748b'}});spentSpan.innerHTML='Spent: <span class="font-mono font-semibold" style="color:'+(s.darkMode?'#fff':'#1e293b')+'">'+formatETB(spent)+'</span>';
      const limSpan=el('span',{style:{color:'#64748b'}});
      limSpan.innerHTML='Limit: <span class="font-mono font-semibold" style="color:'+(s.darkMode?'#fff':'#1e293b')+'">'+formatETB(b.limit)+'</span>';
      row2.append(spentSpan,limSpan);card.appendChild(row2);
      const progRow=el('div',{cls:'flex items-center gap-2'});
      const pWrap=el('div',{style:{flex:1}});pWrap.appendChild(ProgressBar(pct,isOver,isWarn));
      const pPct=el('span',{cls:'text-xs font-mono font-bold',style:{width:'2.5rem',textAlign:'right',flexShrink:0,color:isOver?'#dc2626':isWarn?'#ea580c':'#64748b'}},pct+'%');
      progRow.append(pWrap,pPct);card.appendChild(progRow);list.appendChild(card);
    });
    root.appendChild(list);
    if(editId){
      const editBudget=budgets.find(b=>b.id===editId);
      if(editBudget){
        const bd=el('div',{cls:'modal-backdrop center'});
        const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'24rem',padding:'1.5rem',borderRadius:'1rem'}});
        box.append(el('h3',{cls:'font-bold text-lg mb-1',style:{color:s.darkMode?'#fff':'#0f172a'}},'Edit Budget'),el('p',{cls:'text-sm text-slate-400 mb-4'},editBudget.category));
        const lIn=el('input',{type:'number',min:'1',cls:'input',placeholder:'Monthly limit'});lIn.value=editLimit;lIn.addEventListener('input',e=>editLimit=e.target.value);
        const btns=el('div',{cls:'flex gap-2 mt-4'});
        const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{editId=null;render()});
        const save=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Save');
        save.addEventListener('click',()=>{const n=Number(editLimit);if(n>0){dispatch({type:'UPDATE_BUDGET',payload:{...editBudget,limit:n}});editId=null}});
        btns.append(cancel,save);box.append(lIn,btns);bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){editId=null;render()}});root.appendChild(bd);setTimeout(()=>lIn.focus(),50);
      }
    }
    if(delId){
      const delBudget=budgets.find(b=>b.id===delId);
      const bd=el('div',{cls:'modal-backdrop center'});
      const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'22rem',padding:'1.5rem',borderRadius:'1rem'}});
      box.append(el('h3',{cls:'font-bold text-lg mb-2',style:{color:s.darkMode?'#fff':'#0f172a'}},'Delete budget?'),el('p',{cls:'text-sm mb-5',style:{color:'#64748b'}},delBudget?('Remove the '+delBudget.category+' budget for '+fmtMonthLabel(month)+'. This cannot be undone.'):'This cannot be undone.'));
      const btns=el('div',{cls:'flex gap-3'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{delId=null;render()});
      const confirm=el('button',{style:{flex:1,justifyContent:'center',display:'flex',alignItems:'center',background:'#dc2626',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer'}},'Delete');
      confirm.addEventListener('click',()=>{dispatch({type:'DELETE_BUDGET',payload:delId});delId=null});
      btns.append(cancel,confirm);box.appendChild(btns);bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){delId=null;render()}});root.appendChild(bd);
    }
    renderIcons(root);
  }
  render();
}

// RECURRING
function pgRecurring(root){
  const today=todayStr();
  let showAdd=false,errs={},payRecId=null,editRecId=null,
      form={name:'',amount:'',category:getState().categories[0],frequency:'monthly',startDate:today,endDate:''},
      editForm={};
  function render(){
    const s=getState();
    root.innerHTML='';
    const hdr=el('div',{cls:'flex items-center justify-between mb-4'});
    hdr.appendChild(el('h1',{cls:'section-title'},'Recurring Expenses'));
    const addBtn=el('button',{cls:'btn-primary'});addBtn.append(ic('plus',15),' Add Recurring');addBtn.addEventListener('click',()=>{showAdd=!showAdd;render()});hdr.appendChild(addBtn);root.appendChild(hdr);
    // Due today banner
    const dueToday=s.recurring.filter(r=>r.active&&r.nextDue<=today);
    if(dueToday.length){
      const banner=el('div',{style:{background:s.darkMode?'rgba(120,53,15,.3)':'#fff7ed',border:'1px solid '+(s.darkMode?'rgba(194,65,12,.5)':'#fed7aa'),borderRadius:'1rem',padding:'1rem',marginBottom:'1rem'}});
      const bH=el('div',{cls:'flex items-center gap-2 mb-2'});const calIco=ic('calendar',16);calIco.style.color='#ea580c';bH.append(calIco,el('h3',{cls:'font-semibold',style:{color:s.darkMode?'#fdba74':'#9a3412'}},dueToday.length+' expense'+(dueToday.length>1?'s':'')+' due today'));banner.appendChild(bH);
      dueToday.forEach(r=>{
        const row=el('div',{cls:'flex items-center justify-between rounded-xl p-3',style:{background:s.darkMode?'#1e293b':'#fff',marginBottom:'.5rem'}});
        const info=el('div');info.append(el('p',{cls:'text-sm font-semibold',style:{color:s.darkMode?'#fff':'#1e293b'}},r.name),el('p',{cls:'text-xs text-slate-400'},formatETB(r.amount)+' · '+r.category));
        const btns=el('div',{cls:'flex gap-2'});
        const sk=el('button',{cls:'btn-secondary',style:{paddingTop:'.25rem',paddingBottom:'.25rem',fontSize:'.75rem'}});sk.append(ic('skip-forward',12),' Skip');sk.addEventListener('click',e=>{e.stopPropagation();dispatch({type:'UPDATE_RECURRING',payload:{...r,nextDue:advanceByFrequency(r.nextDue,r.frequency)}})});
        const cf=el('button',{cls:'btn-primary',style:{paddingTop:'.25rem',paddingBottom:'.25rem',fontSize:'.75rem'}});cf.append(ic('check',12),' Confirm');cf.addEventListener('click',e=>{e.stopPropagation();dispatch({type:'ADD_EXPENSE',payload:{amount:r.amount,category:r.category,date:today,note:r.name,receipt:null}});dispatch({type:'UPDATE_RECURRING',payload:{...r,nextDue:advanceByFrequency(r.nextDue,r.frequency)}})});
        btns.append(sk,cf);row.append(info,btns);banner.appendChild(row);
      });
      root.appendChild(banner);
    }
    // Add form — modal
    if(showAdd){
      const bd=el('div',{cls:'modal-backdrop center'});
      const box=el('div',{cls:'modal-box'});
      const mHdr=el('div',{cls:'modal-header'});
      mHdr.append(el('h2',{cls:'font-bold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},'New Recurring Expense'));
      const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',()=>{showAdd=false;render()});mHdr.appendChild(xBtn);
      box.appendChild(mHdr);
      const body=el('div',{style:{padding:'1.25rem'},cls:'space-y-4'});
      // Name field
      const nWrap=el('div');nWrap.appendChild(el('label',{cls:'label'},'Name *'));
      const nIn=el('input',{cls:'input'+(errs.name?' error':''),placeholder:'e.g. Netflix, Rent'});nIn.value=form.name;nIn.addEventListener('input',e=>{form.name=e.target.value;errs.name=''});nWrap.appendChild(nIn);
      if(errs.name)nWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.name));
      body.appendChild(nWrap);
      // Amount field styled like Add Expense
      const amtWrap=el('div');amtWrap.appendChild(el('label',{cls:'label'},'Amount *'));
      const amtRow=el('div',{style:{position:'relative',display:'block'}});
      const etbLbl=el('span',{style:{position:'absolute',left:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#94a3b8',fontFamily:'monospace',fontSize:'.8rem',fontWeight:'600'}});etbLbl.textContent='ETB';
      const aIn=el('input',{type:'number',step:'1',min:'0.01',placeholder:'0',cls:'input'+(errs.amount?' error':''),style:{paddingLeft:'3rem',fontSize:'1.25rem',fontWeight:'700',fontFamily:'monospace'}});aIn.value=form.amount;aIn.addEventListener('input',e=>{form.amount=e.target.value;errs.amount=''});
      amtRow.append(etbLbl,aIn);amtWrap.appendChild(amtRow);
      if(errs.amount)amtWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.amount));
      body.appendChild(amtWrap);
      // Category + Frequency row
      const row1=el('div',{cls:'grid g2 gap-3'});
      const cWrap=buildCategoryField(s.categories,form.category,v=>{form.category=v});
      const fWrap=el('div');fWrap.appendChild(el('label',{cls:'label'},'Frequency'));
      const fSel=el('select',{cls:'input'});Object.entries(FREQ_LABELS).forEach(([k,v])=>{const o=el('option',{value:k},v);if(k===form.frequency)o.selected=true;fSel.appendChild(o)});fSel.addEventListener('change',e=>form.frequency=e.target.value);fWrap.appendChild(fSel);
      row1.append(cWrap,fWrap);body.appendChild(row1);
      // Start + End Date row
      const row2=el('div',{cls:'grid g2 gap-3'});
      const sdWrap=el('div');sdWrap.appendChild(el('label',{cls:'label'},'Start Date'));
      const sdIn=el('input',{type:'date',cls:'input'});sdIn.value=form.startDate;sdIn.addEventListener('change',e=>form.startDate=e.target.value);sdWrap.appendChild(sdIn);
      const edWrap=el('div');edWrap.appendChild(el('label',{cls:'label'},'End Date (optional)'));
      const edIn=el('input',{type:'date',cls:'input'});edIn.value=form.endDate;edIn.addEventListener('change',e=>form.endDate=e.target.value);edWrap.appendChild(edIn);
      row2.append(sdWrap,edWrap);body.appendChild(row2);
      box.appendChild(body);
      const footer=el('div',{cls:'modal-footer'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{showAdd=false;render()});
      const save=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Add Recurring');
      save.addEventListener('click',()=>{errs={};if(!form.name.trim())errs.name='Name is required';if(!isValidAmount(form.amount))errs.amount='Enter a valid amount';if(Object.keys(errs).length){render();return}dispatch({type:'ADD_RECURRING',payload:{name:form.name.trim(),amount:Number(form.amount),category:form.category,frequency:form.frequency,startDate:form.startDate,endDate:form.endDate||null,active:true,nextDue:advanceByFrequency(form.startDate,form.frequency)}});form={name:'',amount:'',category:s.categories[0],frequency:'monthly',startDate:today,endDate:''};showAdd=false;render()});
      footer.append(cancel,save);box.appendChild(footer);
      bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){showAdd=false;render()}});root.appendChild(bd);renderIcons(root);
      setTimeout(()=>aIn.focus(),50);
    }
    if(!s.recurring.length){root.appendChild(el('div',{cls:'empty-state card'},[ic('refresh-cw',32),el('p',{cls:'font-medium text-slate-500 mt-2'},'No recurring expenses yet'),el('p',{cls:'text-sm text-slate-400 mt-1'},'Add subscriptions, rent, gym memberships...')]));renderIcons(root);return}
    const list=el('div',{cls:'space-y-3'});
    s.recurring.forEach(r=>{
      const isDue=r.nextDue<=today;
      const card=el('div',{cls:'card flex items-center gap-4',style:{opacity:r.active?1:.5}});
      const iBox=el('div',{style:{width:'2.5rem',height:'2.5rem',borderRadius:'.75rem',display:'flex',alignItems:'center',justifyContent:'center',flexShrink:0,background:isDue?'#ffedd5':s.darkMode?'rgba(13,148,136,.2)':'#f0fdfa'}});
      const rIco=ic('refresh-cw',18);rIco.style.color=isDue?'#ea580c':'#0d9488';iBox.appendChild(rIco);
      const info=el('div',{style:{flex:1,minWidth:0}});
      const nRow=el('div',{cls:'flex items-center gap-2 flex-wrap'});nRow.append(el('p',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#1e293b'}},r.name),el('span',{cls:FREQ_BADGE[r.frequency]},tFreq(r.frequency)));
      if(isDue)nRow.appendChild(el('span',{cls:'badge badge-orange'},'Due'));
      info.append(nRow,el('p',{cls:'text-xs text-slate-400 mt-1'},r.category+' · Next: '+r.nextDue));
      if(r.endDate)info.appendChild(el('p',{cls:'text-xs text-slate-400'},'Ends: '+r.endDate));
      const rht=el('div',{style:{textAlign:'right',flexShrink:0}});rht.appendChild(el('p',{cls:'font-semibold font-mono',style:{color:s.darkMode?'#fff':'#1e293b'}},formatETB(r.amount)));
      const acts=el('div',{cls:'flex gap-1 mt-1 justify-end'});
      // Edit button — always visible
      const ed=el('button',{cls:'icon-btn',title:'Edit'});ed.appendChild(ic('pencil',14));ed.addEventListener('click',e=>{e.stopPropagation();editRecId=r.id;editForm={name:r.name,amount:String(r.amount),category:r.category,frequency:r.frequency,startDate:r.startDate,endDate:r.endDate||''};errs={};render()});
      // Paid button — only when due (replaces skip)
      if(isDue){
        const pd=el('button',{style:{display:'flex',alignItems:'center',gap:'.2rem',border:'1px solid #059669',color:'#059669',background:'none',borderRadius:'.5rem',padding:'.2rem .5rem',fontSize:'.7rem',fontWeight:'600',cursor:'pointer'}});
        pd.append(ic('check-circle',12),' Paid');pd.addEventListener('click',e=>{e.stopPropagation();payRecId=r.id;render()});
        acts.appendChild(pd);
      } else {
        // Skip only available when NOT due
        const sk=el('button',{cls:'icon-btn',title:'Skip next due date'});sk.appendChild(ic('skip-forward',14));sk.addEventListener('click',e=>{e.stopPropagation();dispatch({type:'UPDATE_RECURRING',payload:{...r,nextDue:advanceByFrequency(r.nextDue,r.frequency)}})});
        acts.appendChild(sk);
      }
      const dl=el('button',{cls:'icon-btn danger'});dl.appendChild(ic('trash-2',14));dl.addEventListener('click',e=>{e.stopPropagation();dispatch({type:'DELETE_RECURRING',payload:r.id})});
      acts.append(ed,dl);rht.appendChild(acts);card.append(iBox,info,rht);list.appendChild(card);
    });
    root.appendChild(list);
    // Pay confirmation modal
    if(payRecId){
      const rec=s.recurring.find(r=>r.id===payRecId);
      if(rec){
        // Advance from today if overdue, otherwise from current nextDue
        const baseDate=rec.nextDue<today?today:rec.nextDue;
        const nextDate=advanceByFrequency(baseDate,rec.frequency);
        const bd=el('div',{cls:'modal-backdrop center'});
        const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'22rem',padding:'1.5rem',borderRadius:'1rem'}});
        const pIco=ic('check-circle',32);pIco.style.cssText='color:#059669;display:block;margin:0 auto .75rem';
        box.append(
          pIco,
          el('h3',{cls:'font-bold text-lg mb-1',style:{color:s.darkMode?'#fff':'#0f172a',textAlign:'center'}},'Confirm Payment?'),
          el('p',{cls:'text-sm mb-1',style:{color:'#64748b',textAlign:'center'}},rec.name),
          el('p',{cls:'text-sm mb-2 font-semibold font-mono',style:{color:'#059669',textAlign:'center'}},formatETB(rec.amount)),
          el('p',{cls:'text-xs mb-5',style:{color:'#94a3b8',textAlign:'center'}},'Records expense · Next due: '+nextDate)
        );
        const btns=el('div',{cls:'flex gap-3'});
        const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{payRecId=null;render()});
        const confirm=el('button',{style:{flex:1,justifyContent:'center',display:'flex',alignItems:'center',background:'#059669',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer'}},'Confirm Paid');
        confirm.addEventListener('click',()=>{
          dispatch({type:'ADD_EXPENSE',payload:{amount:rec.amount,category:rec.category,date:today,note:rec.name,receipt:null}});
          dispatch({type:'UPDATE_RECURRING',payload:{...rec,nextDue:nextDate}});
          payRecId=null;render();
        });
        btns.append(cancel,confirm);box.appendChild(btns);
        bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){payRecId=null;render()}});
        root.appendChild(bd);renderIcons(root);
      }
    }
    // Edit modal
    if(editRecId){
      const rec=s.recurring.find(r=>r.id===editRecId);
      if(rec){
        const bd=el('div',{cls:'modal-backdrop center'});
        const box=el('div',{cls:'modal-box'});
        const mHdr=el('div',{cls:'modal-header'});
        mHdr.append(el('h2',{cls:'font-bold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},'Edit Recurring'));
        const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',()=>{editRecId=null;errs={};render()});mHdr.appendChild(xBtn);
        box.appendChild(mHdr);
        const body=el('div',{style:{padding:'1.25rem'},cls:'space-y-4'});
        // Name
        const nWrap=el('div');nWrap.appendChild(el('label',{cls:'label'},'Name *'));
        const nIn=el('input',{cls:'input'+(errs.name?' error':''),placeholder:'e.g. Netflix, Rent'});nIn.value=editForm.name;nIn.addEventListener('input',e=>{editForm.name=e.target.value;errs.name=''});nWrap.appendChild(nIn);
        if(errs.name)nWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.name));
        body.appendChild(nWrap);
        // Amount
        const amtWrap=el('div');amtWrap.appendChild(el('label',{cls:'label'},'Amount *'));
        const amtRow=el('div',{style:{position:'relative',display:'block'}});
        const etbLbl=el('span',{style:{position:'absolute',left:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#94a3b8',fontFamily:'monospace',fontSize:'.8rem',fontWeight:'600'}});etbLbl.textContent='ETB';
        const aIn=el('input',{type:'number',step:'1',min:'0.01',placeholder:'0',cls:'input'+(errs.amount?' error':''),style:{paddingLeft:'3rem',fontSize:'1.25rem',fontWeight:'700',fontFamily:'monospace'}});aIn.value=editForm.amount;aIn.addEventListener('input',e=>{editForm.amount=e.target.value;errs.amount=''});
        amtRow.append(etbLbl,aIn);amtWrap.appendChild(amtRow);
        if(errs.amount)amtWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.amount));
        body.appendChild(amtWrap);
        // Category + Frequency
        const row1=el('div',{cls:'grid g2 gap-3'});
        const cWrap=buildCategoryField(s.categories,editForm.category,v=>{editForm.category=v});
        const fWrap=el('div');fWrap.appendChild(el('label',{cls:'label'},'Frequency'));
        const fSel=el('select',{cls:'input'});Object.entries(FREQ_LABELS).forEach(([k,v])=>{const o=el('option',{value:k},v);if(k===editForm.frequency)o.selected=true;fSel.appendChild(o)});fSel.addEventListener('change',e=>editForm.frequency=e.target.value);fWrap.appendChild(fSel);
        row1.append(cWrap,fWrap);body.appendChild(row1);
        // Start + End Date
        const row2=el('div',{cls:'grid g2 gap-3'});
        const sdWrap=el('div');sdWrap.appendChild(el('label',{cls:'label'},'Start Date'));
        const sdIn=el('input',{type:'date',cls:'input'});sdIn.value=editForm.startDate;sdIn.addEventListener('change',e=>editForm.startDate=e.target.value);sdWrap.appendChild(sdIn);
        const edWrap=el('div');edWrap.appendChild(el('label',{cls:'label'},'End Date (optional)'));
        const edIn=el('input',{type:'date',cls:'input'});edIn.value=editForm.endDate;edIn.addEventListener('change',e=>editForm.endDate=e.target.value);edWrap.appendChild(edIn);
        row2.append(sdWrap,edWrap);body.appendChild(row2);
        box.appendChild(body);
        const footer=el('div',{cls:'modal-footer'});
        const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{editRecId=null;errs={};render()});
        const save=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Save Changes');
        save.addEventListener('click',()=>{
          errs={};
          if(!editForm.name.trim())errs.name='Name is required';
          if(!isValidAmount(editForm.amount))errs.amount='Enter a valid amount';
          if(Object.keys(errs).length){render();return}
          dispatch({type:'UPDATE_RECURRING',payload:{...rec,name:editForm.name.trim(),amount:Number(editForm.amount),category:editForm.category,frequency:editForm.frequency,startDate:editForm.startDate,endDate:editForm.endDate||null}});
          editRecId=null;errs={};render();
        });
        footer.append(cancel,save);box.appendChild(footer);
        bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){editRecId=null;errs={};render()}});
        root.appendChild(bd);renderIcons(root);
        setTimeout(()=>aIn.focus(),50);
      }
    }
    renderIcons(root);
  }
  render();
}

// â•â•â•â•â•â• BILLS â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function pgBills(root){
  const today=todayStr();let showAdd=false,filter='all',delId=null,payId=null,editId=null,errs={},
    form={name:'',amount:'',dueDate:'',category:'electricity',otherCategory:''},
    editForm={};
  function render(){
    const s=getState();
    root.innerHTML='';
    const counts={overdue:s.bills.filter(b=>b.status==='overdue').length,upcoming:s.bills.filter(b=>b.status==='upcoming').length,paid:s.bills.filter(b=>b.status==='paid').length};
    const rows=s.bills.filter(b=>filter==='all'||b.status===filter).sort((a,b)=>{const o={overdue:0,upcoming:1,paid:2};return(o[a.status]??3)-(o[b.status]??3)||a.dueDate.localeCompare(b.dueDate)});
    const hdr=el('div',{cls:'flex items-center justify-between mb-4'});hdr.appendChild(el('h1',{cls:'section-title'},'Bills Tracker'));
    const addBtn=el('button',{cls:'btn-primary'});addBtn.append(ic('plus',15),' Add Bill');addBtn.addEventListener('click',()=>{showAdd=!showAdd;render()});hdr.appendChild(addBtn);root.appendChild(hdr);
    // Summary
    const sg=el('div',{cls:'grid g3 gap-3 mb-4'});
    [['overdue',counts.overdue,'#dc2626','rgba(220,38,38,.1)'],['upcoming',counts.upcoming,'#f97316','rgba(249,115,22,.1)'],['paid',counts.paid,'#059669','rgba(5,150,105,.1)']].forEach(([key,count,color,bg])=>{
      const cell=el('div',{cls:'card text-center',style:{paddingTop:'.75rem',paddingBottom:'.75rem',cursor:'pointer',border:filter===key?'2px solid '+color:''}});
      cell.addEventListener('click',()=>{filter=filter===key?'all':key;render()});
      cell.append(el('p',{cls:'text-2xl font-bold',style:{color}},String(count)),el('p',{cls:'text-xs capitalize',style:{color:'#64748b'}},key));sg.appendChild(cell);
    });
    root.appendChild(sg);
    // Add form — modal
    if(showAdd){
      const bd=el('div',{cls:'modal-backdrop center'});
      const box=el('div',{cls:'modal-box'});
      const mHdr=el('div',{cls:'modal-header'});
      mHdr.append(el('h2',{cls:'font-bold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},'Add Bill'));
      const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',()=>{showAdd=false;render()});mHdr.appendChild(xBtn);
      box.appendChild(mHdr);
      const body=el('div',{style:{padding:'1.25rem'},cls:'space-y-4'});
      // Bill Name
      const nWrap=el('div');nWrap.appendChild(el('label',{cls:'label'},'Bill Name *'));
      const nIn=el('input',{cls:'input'+(errs.name?' error':''),placeholder:'e.g. Electricity, Internet'});nIn.value=form.name;nIn.addEventListener('input',e=>{form.name=e.target.value;errs.name=''});nWrap.appendChild(nIn);
      if(errs.name)nWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.name));
      body.appendChild(nWrap);
      // Amount
      const amtWrap=el('div');amtWrap.appendChild(el('label',{cls:'label'},'Amount *'));
      const amtRow=el('div',{style:{position:'relative',display:'block'}});
      const etbLbl=el('span',{style:{position:'absolute',left:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#94a3b8',fontFamily:'monospace',fontSize:'.8rem',fontWeight:'600'}});etbLbl.textContent='ETB';
      const aIn=el('input',{type:'number',step:'1',min:'0.01',placeholder:'0',cls:'input'+(errs.amount?' error':''),style:{paddingLeft:'3rem',fontSize:'1.25rem',fontWeight:'700',fontFamily:'monospace'}});aIn.value=form.amount;aIn.addEventListener('input',e=>{form.amount=e.target.value;errs.amount=''});
      amtRow.append(etbLbl,aIn);amtWrap.appendChild(amtRow);
      if(errs.amount)amtWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.amount));
      body.appendChild(amtWrap);
      // Category + Due Date row
      const row1=el('div',{cls:'grid g2 gap-3'});
      const cWrap=el('div');cWrap.appendChild(el('label',{cls:'label'},'Category'));
      const cSel=el('select',{cls:'input'});BILL_CATS.forEach(c=>{const o=el('option',{value:c},c.charAt(0).toUpperCase()+c.slice(1));if(c===form.category)o.selected=true;cSel.appendChild(o)});
      const otherWrap=el('div',{style:{display:form.category==='other'?'block':'none',marginTop:'.5rem'}});
      const otherIn=el('input',{cls:'input'+(errs.otherCategory?' error':''),placeholder:'Describe the bill type'});otherIn.value=form.otherCategory||'';otherIn.addEventListener('input',e=>{form.otherCategory=e.target.value;errs.otherCategory=''});
      if(errs.otherCategory)otherWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.otherCategory));
      otherWrap.appendChild(otherIn);
      cSel.addEventListener('change',e=>{form.category=e.target.value;otherWrap.style.display=form.category==='other'?'block':'none'});
      cWrap.append(cSel,otherWrap);
      const dWrap=el('div');dWrap.appendChild(el('label',{cls:'label'},'Due Date *'));
      const dIn=el('input',{type:'date',cls:'input'+(errs.dueDate?' error':'')});dIn.value=form.dueDate;dIn.addEventListener('change',e=>{form.dueDate=e.target.value;errs.dueDate=''});dWrap.appendChild(dIn);
      if(errs.dueDate)dWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.dueDate));
      row1.append(cWrap,dWrap);body.appendChild(row1);
      box.appendChild(body);
      const footer=el('div',{cls:'modal-footer'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{showAdd=false;render()});
      const save=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Add Bill');
      save.addEventListener('click',()=>{errs={};if(!form.name.trim())errs.name='Bill Name is required';if(!isValidAmount(form.amount))errs.amount='Amount is required';if(!isValidDate(form.dueDate))errs.dueDate='Due Date is required';if(form.category==='other'&&!form.otherCategory?.trim())errs.otherCategory='Please specify the bill type';if(Object.keys(errs).length){render();return}const finalCategory=form.category==='other'?(form.otherCategory.trim()||'other'):form.category;dispatch({type:'ADD_BILL',payload:{...form,category:finalCategory,amount:Number(form.amount),status:form.dueDate<today?'overdue':'upcoming',paidDate:null,reference:null}});form={name:'',amount:'',dueDate:'',category:'electricity',otherCategory:''};showAdd=false;render()});
      footer.append(cancel,save);box.appendChild(footer);
      bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){showAdd=false;render()}});root.appendChild(bd);renderIcons(root);
      setTimeout(()=>aIn.focus(),50);
    }
    // Filter chips
    const chips=el('div',{cls:'flex gap-2 flex-wrap mb-4'});
    ['all','overdue','upcoming','paid'].forEach(f=>{const ch=el('button',{cls:'chip'+(filter===f?' active':'')},f.charAt(0).toUpperCase()+f.slice(1));ch.addEventListener('click',()=>{filter=f;render()});chips.appendChild(ch)});
    root.appendChild(chips);
    // Bills list
    const list=el('div',{cls:'space-y-3'});
    if(!rows.length)list.appendChild(el('div',{cls:'empty-state card'},[ic('zap',32),el('p',{cls:'text-slate-500 mt-2'},'No bills found')]));
    else rows.forEach(bill=>{
      const isOv=bill.status==='overdue',isPaid=bill.status==='paid';
      const {label:dlabel,color:dcolor}=isPaid?{label:'Paid '+(bill.paidDate||''),color:'#059669'}:daysUntilInfo(bill.dueDate);
      const card=el('div',{cls:'card flex items-center gap-4'});
      const iBox=el('div',{style:{width:'2.5rem',height:'2.5rem',borderRadius:'.75rem',display:'flex',alignItems:'center',justifyContent:'center',flexShrink:0,background:isPaid?'#d1fae5':isOv?'#fee2e2':s.darkMode?'rgba(13,148,136,.2)':'#f0fdfa'}});
      iBox.appendChild(billCategoryIcon(bill.category,18));
      const info=el('div',{style:{flex:1,minWidth:0}});
      const nRow=el('div',{cls:'flex items-center gap-2 flex-wrap'});nRow.append(el('p',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#1e293b'}},bill.name));
      if(isPaid)nRow.appendChild(el('span',{cls:'badge badge-green'},'Paid'));
      else if(isOv)nRow.appendChild(el('span',{cls:'badge badge-red'},'Overdue'));
      else nRow.appendChild(el('span',{cls:'badge badge-blue'},'Upcoming'));
      info.append(nRow,el('p',{cls:'text-xs mt-1',style:{color:dcolor}},dlabel));
      if(bill.reference)info.appendChild(el('p',{cls:'text-xs font-mono text-slate-400'},'Ref: '+bill.reference));
      const rht=el('div',{style:{textAlign:'right',flexShrink:0}});rht.appendChild(el('p',{cls:'font-semibold font-mono',style:{color:s.darkMode?'#fff':'#1e293b'}},formatETB(bill.amount)));
      const acts=el('div',{cls:'flex gap-1 mt-1 justify-end'});
      // Edit button — always
      const ed=el('button',{cls:'icon-btn',title:'Edit'});ed.appendChild(ic('pencil',13));ed.addEventListener('click',e=>{e.stopPropagation();editId=bill.id;editForm={name:bill.name,amount:String(bill.amount),dueDate:bill.dueDate,category:bill.category,otherCategory:''};errs={};render()});
      acts.appendChild(ed);
      // Paid button — only if not already paid
      if(!isPaid){const pd=el('button',{style:{display:'flex',alignItems:'center',gap:'.2rem',border:'1px solid #059669',color:'#059669',background:'none',borderRadius:'.5rem',padding:'.2rem .5rem',fontSize:'.7rem',fontWeight:'600',cursor:'pointer'}});pd.append(ic('check-circle',12),' Paid');pd.addEventListener('click',e=>{e.stopPropagation();payId=bill.id;render()});acts.appendChild(pd);}
      const dl=el('button',{cls:'icon-btn danger'});dl.appendChild(ic('trash-2',13));dl.addEventListener('click',e=>{e.stopPropagation();delId=bill.id;render()});
      acts.appendChild(dl);rht.appendChild(acts);card.append(iBox,info,rht);list.appendChild(card);
    });
    root.appendChild(list);
    // Pay confirmation modal
    if(payId){
      const bill=s.bills.find(b=>b.id===payId);
      if(bill){
        const bd=el('div',{cls:'modal-backdrop center'});
        const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'22rem',padding:'1.5rem',borderRadius:'1rem'}});
        const pIco=ic('check-circle',32);pIco.style.cssText='color:#059669;display:block;margin:0 auto .75rem';
        box.append(
          pIco,
          el('h3',{cls:'font-bold text-lg mb-1',style:{color:s.darkMode?'#fff':'#0f172a',textAlign:'center'}},'Mark as Paid?'),
          el('p',{cls:'text-sm mb-1',style:{color:'#64748b',textAlign:'center'}},bill.name),
          el('p',{cls:'text-sm mb-1',style:{color:'#64748b',textAlign:'center'}},'Due: '+bill.dueDate),
          el('p',{cls:'text-sm mb-5 font-semibold font-mono',style:{color:'#059669',textAlign:'center'}},formatETB(bill.amount))
        );
        const btns=el('div',{cls:'flex gap-3'});
        const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{payId=null;render()});
        const confirm=el('button',{style:{flex:1,justifyContent:'center',display:'flex',alignItems:'center',background:'#059669',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer'}},'Confirm Paid');
        confirm.addEventListener('click',()=>{dispatch({type:'UPDATE_BILL',payload:{...bill,status:'paid',paidDate:today}});payId=null;render()});
        btns.append(cancel,confirm);box.appendChild(btns);
        bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){payId=null;render()}});
        root.appendChild(bd);renderIcons(root);
      }
    }
    // Edit modal
    if(editId){
      const bill=s.bills.find(b=>b.id===editId);
      if(bill){
        const bd=el('div',{cls:'modal-backdrop center'});
        const box=el('div',{cls:'modal-box'});
        const mHdr=el('div',{cls:'modal-header'});
        mHdr.append(el('h2',{cls:'font-bold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},'Edit Bill'));
        const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',()=>{editId=null;errs={};render()});mHdr.appendChild(xBtn);
        box.appendChild(mHdr);
        const body=el('div',{style:{padding:'1.25rem'},cls:'space-y-4'});
        // Name
        const nWrap=el('div');nWrap.appendChild(el('label',{cls:'label'},'Bill Name *'));
        const nIn=el('input',{cls:'input'+(errs.name?' error':''),placeholder:'e.g. Electricity, Internet'});nIn.value=editForm.name;nIn.addEventListener('input',e=>{editForm.name=e.target.value;errs.name=''});nWrap.appendChild(nIn);
        if(errs.name)nWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.name));
        body.appendChild(nWrap);
        // Amount
        const amtWrap=el('div');amtWrap.appendChild(el('label',{cls:'label'},'Amount *'));
        const amtRow=el('div',{style:{position:'relative',display:'block'}});
        const etbLbl=el('span',{style:{position:'absolute',left:'.75rem',top:'50%',transform:'translateY(-50%)',color:'#94a3b8',fontFamily:'monospace',fontSize:'.8rem',fontWeight:'600'}});etbLbl.textContent='ETB';
        const aIn=el('input',{type:'number',step:'1',min:'0.01',placeholder:'0',cls:'input'+(errs.amount?' error':''),style:{paddingLeft:'3rem',fontSize:'1.25rem',fontWeight:'700',fontFamily:'monospace'}});aIn.value=editForm.amount;aIn.addEventListener('input',e=>{editForm.amount=e.target.value;errs.amount=''});
        amtRow.append(etbLbl,aIn);amtWrap.appendChild(amtRow);
        if(errs.amount)amtWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.amount));
        body.appendChild(amtWrap);
        // Category + Due Date
        const row1=el('div',{cls:'grid g2 gap-3'});
        const cWrap=el('div');cWrap.appendChild(el('label',{cls:'label'},'Category'));
        const cSel=el('select',{cls:'input'});
        const knownCats=BILL_CATS.filter(c=>c!=='other');
        const isCustom=!knownCats.includes(editForm.category);
        const catList=isCustom?[...BILL_CATS]:BILL_CATS;
        catList.forEach(c=>{const o=el('option',{value:c},c.charAt(0).toUpperCase()+c.slice(1));if(c===editForm.category||(isCustom&&c==='other'))o.selected=true;cSel.appendChild(o)});
        if(isCustom){const o=el('option',{value:editForm.category},editForm.category);o.selected=true;cSel.insertBefore(o,cSel.firstChild)}
        cSel.addEventListener('change',e=>{editForm.category=e.target.value});
        cWrap.appendChild(cSel);
        const dWrap=el('div');dWrap.appendChild(el('label',{cls:'label'},'Due Date *'));
        const dIn=el('input',{type:'date',cls:'input'+(errs.dueDate?' error':'')});dIn.value=editForm.dueDate;dIn.addEventListener('change',e=>{editForm.dueDate=e.target.value;errs.dueDate=''});dWrap.appendChild(dIn);
        if(errs.dueDate)dWrap.appendChild(el('p',{cls:'text-xs mt-1',style:{color:'#ef4444'}},errs.dueDate));
        row1.append(cWrap,dWrap);body.appendChild(row1);
        box.appendChild(body);
        const footer=el('div',{cls:'modal-footer'});
        const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{editId=null;errs={};render()});
        const save=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Save Changes');
        save.addEventListener('click',()=>{
          errs={};
          if(!editForm.name.trim())errs.name='Bill Name is required';
          if(!isValidAmount(editForm.amount))errs.amount='Amount is required';
          if(!isValidDate(editForm.dueDate))errs.dueDate='Due Date is required';
          if(Object.keys(errs).length){render();return}
          // Recalculate status based on new due date (only if not already paid)
          const newStatus=bill.status==='paid'?'paid':(editForm.dueDate<today?'overdue':'upcoming');
          dispatch({type:'UPDATE_BILL',payload:{...bill,name:editForm.name.trim(),amount:Number(editForm.amount),dueDate:editForm.dueDate,category:editForm.category,status:newStatus}});
          editId=null;errs={};render();
        });
        footer.append(cancel,save);box.appendChild(footer);
        bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){editId=null;errs={};render()}});
        root.appendChild(bd);renderIcons(root);
        setTimeout(()=>nIn.focus(),50);
      }
    }
    if(delId){
      const bd=el('div',{cls:'modal-backdrop center'});
      const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'22rem',padding:'1.5rem',borderRadius:'1rem'}});
      box.append(el('h3',{cls:'font-bold text-lg mb-2',style:{color:s.darkMode?'#fff':'#0f172a'}},'Delete bill?'),el('p',{cls:'text-sm mb-5',style:{color:'#64748b'}},'This cannot be undone.'));
      const btns=el('div',{cls:'flex gap-3'});
      const cancel=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancel.addEventListener('click',()=>{delId=null;render()});
      const confirm=el('button',{style:{flex:1,justifyContent:'center',display:'flex',alignItems:'center',background:'#dc2626',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer'}},'Delete');
      confirm.addEventListener('click',()=>{dispatch({type:'DELETE_BILL',payload:delId});delId=null;render()});
      btns.append(cancel,confirm);box.appendChild(btns);bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd){delId=null;render()}});root.appendChild(bd);
    }
    renderIcons(root);
  }
  render();
}

// REPORTS
let reportChart=null;
function pgReports(root){
  const today=todayStr();
  // Date range modes: 'month' (single month picker) or 'range' (custom start/end)
  let tab='trend',month=currentMonthStr(),dateMode='month',rangeStart='',rangeEnd='';
  // Data filter toggles
  let inclExpenses=true,inclBills=true,inclRecurring=true,inclBudgets=true;

  function getDateRange(){
    if(dateMode==='month'){
      const y=month.split('-')[0],m=month.split('-')[1];
      const daysInM=new Date(Number(y),Number(m),0).getDate();
      return{from:month+'-01',to:month+'-'+String(daysInM).padStart(2,'0'),label:fmtMonthLabel(month)};
    }
    const from=rangeStart||'1900-01-01',to=rangeEnd||today;
    const fmtD=d=>new Date(d+'T00:00:00').toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'});
    return{from,to,label:(rangeStart?fmtD(rangeStart):'All time')+' — '+(rangeEnd?fmtD(rangeEnd):'Today')};
  }

  function inRange(date,range){return date>=range.from&&date<=range.to}

  function render(){
    const s=getState();
    root.innerHTML='';
    if(reportChart){try{reportChart.destroy()}catch{}reportChart=null}
    const range=getDateRange();

    // Filtered data sets
    const mExp=inclExpenses?s.expenses.filter(e=>inRange(e.date,range)):[];
    const mBills=inclBills?s.bills.filter(b=>{const d=b.paidDate||b.dueDate;return d&&inRange(d,range)}):[]; 
    const mRecurring=inclRecurring?s.recurring.filter(r=>r.active&&inRange(r.startDate||today,range)):[];
    const mBudgets=inclBudgets?(dateMode==='month'?s.budgets.filter(b=>b.month===month):[]):[];

    const mTotal=mExp.reduce((a,e)=>a+Number(e.amount),0);
    const billsTotal=mBills.reduce((a,b)=>a+Number(b.amount),0);
    const recurringTotal=mRecurring.reduce((a,r)=>a+Number(r.amount),0);
    const budgetTotal=mBudgets.reduce((a,b)=>a+Number(b.limit),0);

    const rangeDays=Math.max(1,Math.round((new Date(range.to+'T00:00:00')-new Date(range.from+'T00:00:00'))/86400000)+1);
    const avgD=rangeDays>0?mTotal/rangeDays:0;
    const catMap={};mExp.forEach(e=>catMap[e.category]=(catMap[e.category]||0)+Number(e.amount));
    const cats=Object.entries(catMap).sort((a,b)=>b[1]-a[1]);
    const topCat=cats[0]?.[0]||'—';

    // ── Header ──
    const hdr=el('div',{cls:'flex items-center justify-between flex-wrap gap-3 mb-4'});
    hdr.appendChild(el('h1',{cls:'section-title'},'Reports'));
    // Export button — top right; handler updated after data is computed, stored by ref
    const exportBtn=el('button',{cls:'btn-secondary'});exportBtn.append(ic('download',15),' Export CSV');
    exportBtn.addEventListener('click',()=>{
      const s2=getState();
      // Build rows from whatever data is in scope
      const rows=[['Date','Name/Note','Category','Amount','Status','Type']];
      if(inclExpenses)s2.expenses.filter(e=>{const d=e.date;return(!rangeStart||d>=rangeStart)&&(!rangeEnd||d<=rangeEnd)&&(dateMode!=='month'||d.startsWith(month))}).forEach(e=>rows.push([e.date,'\"'+(e.note||'').replace(/\"/g,'\"\"')+'\"',e.category,e.amount,'—','Expense']));
      if(inclBills)s2.bills.filter(b=>{const d=b.paidDate||b.dueDate;return d&&(!rangeStart||d>=rangeStart)&&(!rangeEnd||d<=rangeEnd)&&(dateMode!=='month'||d.startsWith(month))}).forEach(b=>rows.push([b.paidDate||b.dueDate,'\"'+b.name+'\"',b.category,b.amount,b.status,'Bill']));
      if(inclRecurring)s2.recurring.filter(r=>{const d=r.startDate;return r.active&&(!rangeStart||d>=rangeStart)&&(!rangeEnd||d<=rangeEnd)&&(dateMode!=='month'||d.startsWith(month))}).forEach(r=>rows.push([r.startDate,'\"'+r.name+'\"',r.category,r.amount,'recurring','Recurring']));
      const csv=rows.map(r=>r.join(',')).join('\n');
      const a=document.createElement('a');a.href='data:text/csv,'+encodeURIComponent(csv);
      a.download='report-'+(dateMode==='month'?month:(rangeStart||'all')+'-to-'+(rangeEnd||'today'))+'.csv';
      document.body.appendChild(a);a.click();a.remove();
    });
    hdr.appendChild(exportBtn);
    root.appendChild(hdr);

    // ── Filter Panel ──
    const filterCard=el('div',{cls:'card mb-4',style:{padding:'1rem'}});
    filterCard.appendChild(el('p',{cls:'text-xs font-semibold mb-3',style:{color:'#94a3b8',textTransform:'uppercase',letterSpacing:'.05em'}},'Report Filters'));

    // Date mode toggle
    const modeRow=el('div',{cls:'flex gap-2 mb-3'});
    ['month','range'].forEach(mode=>{
      const btn=el('button',{style:{flex:1,padding:'.375rem .75rem',borderRadius:'.5rem',fontSize:'.8rem',fontWeight:'600',border:'1px solid '+(s.darkMode?'#334155':'#e2e8f0'),cursor:'pointer',background:dateMode===mode?'#0d9488':'transparent',color:dateMode===mode?'#fff':(s.darkMode?'#94a3b8':'#64748b'),transition:'all .15s'}},mode==='month'?'Monthly View':'Custom Date Range');
      btn.addEventListener('click',()=>{dateMode=mode;render()});modeRow.appendChild(btn);
    });
    filterCard.appendChild(modeRow);

    // Date inputs
    if(dateMode==='month'){
      const mIn=el('input',{type:'month',cls:'input',style:{width:'auto',paddingTop:'.375rem',paddingBottom:'.375rem',fontSize:'.875rem'}});
      mIn.value=month;mIn.addEventListener('change',e=>{month=e.target.value;render()});
      filterCard.appendChild(mIn);
    }else{
      const rangeRow=el('div',{style:{display:'grid',gridTemplateColumns:'1fr auto 1fr',gap:'.5rem',alignItems:'center'}});
      const sIn=el('input',{type:'date',cls:'input',style:{fontSize:'.875rem'}});sIn.value=rangeStart;sIn.addEventListener('change',e=>{rangeStart=e.target.value;render()});
      const sep=el('span',{style:{textAlign:'center',color:'#94a3b8',fontSize:'.8rem',flexShrink:0}},'to');
      const eIn=el('input',{type:'date',cls:'input',style:{fontSize:'.875rem'}});eIn.value=rangeEnd;eIn.max=today;eIn.addEventListener('change',e=>{rangeEnd=e.target.value;render()});
      rangeRow.append(sIn,sep,eIn);filterCard.appendChild(rangeRow);
      // Quick range presets
      const presets=el('div',{cls:'flex gap-2 flex-wrap mt-2'});
      [['Last 7d',7],['Last 30d',30],['Last 90d',90],['This Year',365]].forEach(([lbl,days])=>{
        const btn=el('button',{style:{padding:'.2rem .6rem',borderRadius:'.4rem',fontSize:'.75rem',fontWeight:'500',border:'1px solid '+(s.darkMode?'#334155':'#e2e8f0'),background:'transparent',color:'#64748b',cursor:'pointer'}},lbl);
        btn.addEventListener('click',()=>{
          const d=new Date();d.setDate(d.getDate()-days+1);
          rangeStart=fmtDate(d);rangeEnd=today;render();
        });
        presets.appendChild(btn);
      });
      // This year preset
      const thisYearBtn=el('button',{style:{padding:'.2rem .6rem',borderRadius:'.4rem',fontSize:'.75rem',fontWeight:'500',border:'1px solid '+(s.darkMode?'#334155':'#e2e8f0'),background:'transparent',color:'#64748b',cursor:'pointer'}},'Year to Date');
      thisYearBtn.addEventListener('click',()=>{rangeStart=new Date().getFullYear()+'-01-01';rangeEnd=today;render()});
      presets.appendChild(thisYearBtn);
      filterCard.appendChild(presets);
    }

    // Data type toggles
    const typeRow=el('div',{style:{display:'flex',gap:'.5rem',flexWrap:'wrap',marginTop:'.75rem',paddingTop:'.75rem',borderTop:'1px solid '+(s.darkMode?'#1e293b':'#f1f5f9')}});
    typeRow.appendChild(el('span',{style:{fontSize:'.75rem',fontWeight:'600',color:'#94a3b8',alignSelf:'center',marginRight:'.25rem'}},'Include:'));
    const toggleDef=[['Expenses','inclExpenses',inclExpenses,'#0d9488'],['Bills','inclBills',inclBills,'#f97316'],['Recurring','inclRecurring',inclRecurring,'#8b5cf6'],['Budgets','inclBudgets',inclBudgets,'#06b6d4']];
    toggleDef.forEach(([lbl,key,val,color])=>{
      const btn=el('button',{style:{padding:'.25rem .65rem',borderRadius:'999px',fontSize:'.75rem',fontWeight:'600',border:'2px solid '+color,cursor:'pointer',background:val?color:'transparent',color:val?'#fff':color,transition:'all .12s'}},lbl);
      btn.addEventListener('click',()=>{
        if(key==='inclExpenses')inclExpenses=!inclExpenses;
        else if(key==='inclBills')inclBills=!inclBills;
        else if(key==='inclRecurring')inclRecurring=!inclRecurring;
        else if(key==='inclBudgets')inclBudgets=!inclBudgets;
        render();
      });
      typeRow.appendChild(btn);
    });
    filterCard.appendChild(typeRow);
    root.appendChild(filterCard);

    // ── Summary cards ──
    const sg=el('div',{cls:'grid g4 gap-3 mb-4'});
    const summaryItems=[];
    if(inclExpenses)summaryItems.push([formatETB(mTotal,2),'Expenses',true,'#0d9488']);
    if(inclBills)summaryItems.push([formatETB(billsTotal,2),'Bills',true,'#f97316']);
    if(inclRecurring)summaryItems.push([formatETB(recurringTotal,2),'Recurring',true,'#8b5cf6']);
    if(inclBudgets&&dateMode==='month')summaryItems.push([formatETB(budgetTotal,2),'Budgeted',true,'#06b6d4']);
    if(inclExpenses)summaryItems.push([formatETB(avgD,2),'Avg/Day',true,'#64748b']);
    if(inclExpenses)summaryItems.push([String(mExp.length),'Transactions',false,'#64748b']);
    if(inclExpenses)summaryItems.push([topCat,'Top Category',false,'#64748b']);
    summaryItems.forEach(([val,lbl,mono,color])=>{
      const cell=el('div',{cls:'card text-center'});
      cell.append(el('p',{cls:'text-xl font-bold truncate px-1'+(mono?' font-mono':''),style:{color}},val),el('p',{cls:'text-xs text-slate-400'},lbl));
      sg.appendChild(cell);
    });
    root.appendChild(sg);

    // ── Date range label ──
    root.appendChild(el('p',{cls:'text-xs text-slate-400 mb-3',style:{textAlign:'right'}},range.label));

    // ── Tab switcher ──
    const tabs=el('div',{style:{display:'flex',gap:'.25rem',background:s.darkMode?'#1e293b':'#f1f5f9',borderRadius:'.75rem',padding:'.25rem',marginBottom:'1rem',width:'fit-content',flexWrap:'wrap'}});
    [['trend','Trend','trending-up'],['category','By Category','pie-chart'],['monthly','6 Months','bar-chart-2'],['data','Data Table','table']].forEach(([key,lbl,ico])=>{
      const btn=el('button',{style:{display:'flex',alignItems:'center',gap:'.375rem',padding:'.375rem .75rem',borderRadius:'.5rem',fontSize:'.875rem',fontWeight:'500',transition:'all .15s',border:'none',cursor:'pointer',whiteSpace:'nowrap'}});
      btn.append(ic(ico,14),' '+lbl);
      btn.style.cssText+=tab===key?';background:'+(s.darkMode?'#334155':'#fff')+';color:#0f766e;box-shadow:0 1px 2px rgba(0,0,0,.1)':';background:transparent;color:#64748b';
      btn.addEventListener('click',()=>{tab=key;render()});tabs.appendChild(btn);
    });
    root.appendChild(tabs);

    if(tab==='data'){
      // ── Data Table tab: all filtered data across types ──
      const sections=[
        inclExpenses&&mExp.length?{title:'Expenses ('+mExp.length+')',color:'#0d9488',rows:mExp.map(e=>({date:e.date,name:e.note||e.category,category:e.category,amount:e.amount,status:'—',type:'Expense'}))}:null,
        inclBills&&mBills.length?{title:'Bills ('+mBills.length+')',color:'#f97316',rows:mBills.map(b=>({date:b.paidDate||b.dueDate,name:b.name,category:b.category,amount:b.amount,status:b.status,type:'Bill'}))}:null,
        inclRecurring&&mRecurring.length?{title:'Recurring ('+mRecurring.length+')',color:'#8b5cf6',rows:mRecurring.map(r=>({date:r.startDate,name:r.name,category:r.category,amount:r.amount,status:r.frequency,type:'Recurring'}))}:null,
        inclBudgets&&mBudgets.length&&dateMode==='month'?{title:'Budgets ('+mBudgets.length+')',color:'#06b6d4',rows:mBudgets.map(b=>({date:month,name:b.category+' Budget',category:b.category,amount:b.limit,status:'limit',type:'Budget'}))}:null,
      ].filter(Boolean);

      if(!sections.length){root.appendChild(el('div',{cls:'empty-state card'},[ic('table',32),el('p',{cls:'text-slate-400 mt-2'},'No data in selected range')]));renderIcons(root);return}

      sections.forEach(sec=>{
        const secCard=el('div',{cls:'card p-0 overflow-hidden mb-4'});
        const secHdr=el('div',{style:{padding:'.75rem 1rem',borderBottom:'1px solid '+(s.darkMode?'#1e293b':'#f1f5f9'),display:'flex',alignItems:'center',gap:'.5rem'}});
        const dot=el('div',{style:{width:'.75rem',height:'.75rem',borderRadius:'9999px',background:sec.color,flexShrink:0}});
        secHdr.append(dot,el('span',{cls:'font-semibold text-sm',style:{color:s.darkMode?'#fff':'#0f172a'}},sec.title));
        secCard.appendChild(secHdr);
        const tbl=document.createElement('table');tbl.style.width='100%';
        const thead=document.createElement('thead');const thr=document.createElement('tr');
        ['Date','Name','Category','Amount','Status'].forEach((h,i)=>{const th=document.createElement('th');th.textContent=h;th.style.textAlign=i>=3?'right':'left';th.style.padding='.5rem .75rem';th.style.fontSize='.75rem';thr.appendChild(th)});
        thead.appendChild(thr);tbl.appendChild(thead);
        const tbody=document.createElement('tbody');
        sec.rows.sort((a,b)=>b.date.localeCompare(a.date)).forEach(row=>{
          const tr=document.createElement('tr');
          const makeCell=(txt,align='left',mono=false)=>{const td=document.createElement('td');td.textContent=txt;td.style.cssText='padding:.5rem .75rem;font-size:.8rem;text-align:'+align+(mono?';font-family:monospace':'');return td};
          tr.append(makeCell(row.date),makeCell(row.name),makeCell(row.category),makeCell(formatETB(row.amount,2),'right',true),makeCell(row.status,'right'));
          tbody.appendChild(tr);
        });
        tbl.appendChild(tbody);secCard.appendChild(tbl);root.appendChild(secCard);
      });

      renderIcons(root);return;
    }

    // ── Chart card ──
    const chartCard=el('div',{cls:'card mb-4',style:{padding:'1rem'}});
    const canvas=document.createElement('canvas');canvas.id='report-chart';canvas.style.cssText='width:100%;height:300px';
    chartCard.appendChild(canvas);root.appendChild(chartCard);
    const gridColor=s.darkMode?'rgba(51,65,85,.5)':'#f1f5f9';
    const tickColor=s.darkMode?'#64748b':'#94a3b8';
    const baseScales={x:{grid:{display:false},ticks:{color:tickColor}},y:{grid:{color:gridColor},ticks:{color:tickColor,callback:v=>'ETB '+v}}};
    const baseLegend={position:'bottom',labels:{boxWidth:12,padding:10,color:s.darkMode?'#94a3b8':'#64748b'}};

    setTimeout(()=>{
      const ctx=document.getElementById('report-chart')?.getContext('2d');if(!ctx)return;
      if(tab==='trend'){
        // Build day-by-day data for expenses (and optionally bills)
        const dayMap={};
        if(inclExpenses)mExp.forEach(e=>{dayMap[e.date]=(dayMap[e.date]||0)+Number(e.amount)});
        const allDates=[];let cur=new Date(range.from+'T00:00:00'),end=new Date(range.to+'T00:00:00');
        // Cap to 90 days for readability
        const capDays=90;let capped=false;
        if((end-cur)/86400000>capDays){cur=new Date(end.getTime()-capDays*86400000);capped=true}
        while(cur<=end){allDates.push(fmtDate(cur));cur.setDate(cur.getDate()+1)}
        const labels=allDates.map(d=>d.slice(5));// MM-DD
        const data=allDates.map(d=>dayMap[d]||0);
        const datasets=[{label:'Daily Expenses',data,borderColor:'#0d9488',backgroundColor:'rgba(13,148,136,.08)',fill:true,tension:.4,pointRadius:allDates.length>30?0:2,pointBackgroundColor:'#0d9488'}];
        if(inclBills){
          const billMap={};mBills.forEach(b=>{const d=b.paidDate||b.dueDate;if(d)billMap[d]=(billMap[d]||0)+Number(b.amount)});
          datasets.push({label:'Bills',data:allDates.map(d=>billMap[d]||0),borderColor:'#f97316',backgroundColor:'rgba(249,115,22,.06)',fill:true,tension:.4,pointRadius:0});
        }
        reportChart=new Chart(ctx,{type:'line',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{...baseLegend,display:datasets.length>1},title:{display:true,text:(capped?'Last 90 Days — ':'Daily Spending — ')+range.label,color:s.darkMode?'#cbd5e1':'#334155',font:{size:13}}},scales:baseScales}});
      }else if(tab==='category'){
        if(!cats.length){chartCard.innerHTML='<div style="height:300px;display:flex;align-items:center;justify-content:center;color:#94a3b8">No expense data in this range</div>';return}
        // Build combined category data incl bills if toggled
        const combinedMap={...catMap};
        if(inclBills)mBills.forEach(b=>{const k='Bill: '+(b.category||b.name);combinedMap[k]=(combinedMap[k]||0)+Number(b.amount)});
        const combined=Object.entries(combinedMap).sort((a,b)=>b[1]-a[1]);
        reportChart=new Chart(ctx,{type:'doughnut',data:{labels:combined.map(c=>c[0]),datasets:[{data:combined.map(c=>c[1]),backgroundColor:CHART_COLORS,borderWidth:0,hoverOffset:8}]},options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{...baseLegend,position:'right'},title:{display:true,text:'Spending by Category',color:s.darkMode?'#cbd5e1':'#334155',font:{size:13}}}}});
      }else{
        // 6-month bar — expenses + bills stacked
        const months6=[],expData=[],billData=[];
        for(let i=5;i>=0;i--){const d=new Date();d.setMonth(d.getMonth()-i);const ms=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');months6.push(d.toLocaleDateString('en',{month:'short',year:'2-digit'}));expData.push(inclExpenses?s.expenses.filter(e=>e.date.startsWith(ms)).reduce((a,e)=>a+Number(e.amount),0):0);billData.push(inclBills?s.bills.filter(b=>{const pd=b.paidDate||b.dueDate;return pd&&pd.startsWith(ms)}).reduce((a,b)=>a+Number(b.amount),0):0)}
        const datasets2=[{label:'Expenses',data:expData,backgroundColor:months6.map((_,i)=>i===5?'#0d9488':'#ccfbf1'),borderRadius:4,borderSkipped:false}];
        if(inclBills)datasets2.push({label:'Bills',data:billData,backgroundColor:months6.map((_,i)=>i===5?'#f97316':'#fed7aa'),borderRadius:4,borderSkipped:false});
        reportChart=new Chart(ctx,{type:'bar',data:{labels:months6,datasets:datasets2},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{...baseLegend,display:inclBills},title:{display:true,text:'Last 6 Months',color:s.darkMode?'#cbd5e1':'#334155',font:{size:13}}},scales:{...baseScales,x:{...baseScales.x,stacked:true},y:{...baseScales.y,stacked:true}}}});
      }
    },50);

    // ── Category breakdown table (expenses) ──
    const combinedTotal=mTotal+billsTotal;
    const allCatItems=[];
    if(inclExpenses)cats.forEach(([cat,amt],i)=>allCatItems.push({cat,amt,color:CHART_COLORS[i%CHART_COLORS.length],count:mExp.filter(e=>e.category===cat).length,type:'Expense'}));
    if(inclBills)mBills.forEach((b,i)=>allCatItems.push({cat:'Bill: '+b.name,amt:Number(b.amount),color:'#f97316',count:1,type:'Bill'}));
    if(allCatItems.length&&combinedTotal>0){
      const tableCard=el('div',{cls:'card p-0 overflow-hidden'});
      const tbl=document.createElement('table');tbl.style.width='100%';
      const thead=document.createElement('thead');const thr=document.createElement('tr');
      ['Category','Amount','% Share','Count'].forEach((h,i)=>{const th=document.createElement('th');th.textContent=h;th.style.cssText='text-align:'+(i===0?'left':'right')+';padding:.6rem .75rem;font-size:.75rem';thr.appendChild(th)});
      thead.appendChild(thr);tbl.appendChild(thead);
      const tbody=document.createElement('tbody');
      allCatItems.sort((a,b)=>b.amt-a.amt).forEach(({cat,amt,color,count})=>{
        const tr=document.createElement('tr');
        const td1=document.createElement('td');td1.style.cssText='padding:.5rem .75rem;text-align:left;font-size:.8rem';const dot=el('div',{style:{width:'.625rem',height:'.625rem',borderRadius:'9999px',background:color,display:'inline-block',marginRight:'.5rem'}});td1.append(dot,cat);
        const td2=document.createElement('td');td2.textContent=formatETB(amt,2);td2.style.cssText='text-align:right;font-family:monospace;padding:.5rem .75rem;font-size:.8rem';
        const td3=document.createElement('td');td3.textContent=(amt/combinedTotal*100).toFixed(1)+'%';td3.style.cssText='text-align:right;color:#64748b;padding:.5rem .75rem;font-size:.8rem';
        const td4=document.createElement('td');td4.textContent=String(count);td4.style.cssText='text-align:right;color:#64748b;padding:.5rem .75rem;font-size:.8rem';
        tr.append(td1,td2,td3,td4);tbody.appendChild(tr);
      });
      tbl.appendChild(tbody);tableCard.appendChild(tbl);root.appendChild(tableCard);
    }

    renderIcons(root);
  }
  render();
}

// â•â•â•â•â•â• PROFILE â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function pgProfile(root){
  const authUser=Auth.session();let tab=profileTab,saved=false;
  let nameVal=authUser?.name||getState().user.name;
  let showReset=false,fStep='send',fEmail=authUser?.email||'',fOtp='',fOtpInput='',fNewPw='',fConfPw='',fErr='',fTimer=0,fTint=null;
  let securityTimerButtons=[];
  function stopSecurityTimer(){if(fTint){clearInterval(fTint);fTint=null}}
  function syncSecurityTimerButtons(){
    securityTimerButtons=securityTimerButtons.filter(btn=>btn&&btn.isConnected);
    securityTimerButtons.forEach(btn=>{
      btn.disabled=fTimer>0;
      btn.textContent=fTimer>0?fTimer+'s':'Resend';
    });
  }
  function startSecurityTimer(){
    stopSecurityTimer();
    syncSecurityTimerButtons();
    if(fTimer<=0)return;
    fTint=setInterval(()=>{
      fTimer=Math.max(0,fTimer-1);
      syncSecurityTimerButtons();
      if(fTimer<=0)stopSecurityTimer();
    },1000);
  }
  const TABS=[{key:'account',label:'Account',icon:'user'},{key:'security',label:'Security',icon:'shield'},{key:'receipts',label:'Receipts',icon:'image'},{key:'notifications',label:'Notifications',icon:'bell'}];
  function render(){
    const s=getState();
    if(tab!=='security'||fStep!=='verify')stopSecurityTimer();
    securityTimerButtons=[];
    root.innerHTML='';root.appendChild(el('h1',{cls:'section-title mb-4'},'Profile & Settings'));
    // Tab nav
    const tNav=el('div',{style:{display:'flex',gap:'.25rem',background:s.darkMode?'#1e293b':'#f1f5f9',borderRadius:'.75rem',padding:'.25rem',marginBottom:'1.25rem',overflowX:'auto'}});
    TABS.forEach(({key,label,icon})=>{
      const btn=el('button',{style:{display:'flex',alignItems:'center',gap:'.375rem',padding:'.375rem .75rem',borderRadius:'.5rem',fontSize:'.875rem',fontWeight:'500',whiteSpace:'nowrap',transition:'all .15s',border:'none',cursor:'pointer',flexShrink:0}});
      btn.append(ic(icon,14),' '+label);btn.style.cssText+=tab===key?';background:'+(s.darkMode?'#334155':'#fff')+';color:#0f766e;box-shadow:0 1px 2px rgba(0,0,0,.1)':';background:transparent;color:#64748b';
      btn.addEventListener('click',()=>{profileTab=key;tab=key;render()});tNav.appendChild(btn);
    });root.appendChild(tNav);
    const avatarImg=s.user.avatar||authUser?.avatar;const displayName=nameVal||'User';
    if(tab==='account'){
      // Avatar card
      const avCard=el('div',{cls:'card flex items-center gap-4 mb-4'});
      const avWrap=el('div',{cls:'relative'});
      const avBox=el('div',{style:{width:'5rem',height:'5rem',borderRadius:'1rem',background:s.darkMode?'rgba(13,148,136,.2)':'#ccfbf1',display:'flex',alignItems:'center',justifyContent:'center',overflow:'hidden'}});
      if(avatarImg)avBox.appendChild(el('img',{src:avatarImg,alt:'Avatar',style:{width:'100%',height:'100%',objectFit:'cover'}}));
      else avBox.appendChild(el('span',{style:{fontSize:'2rem',fontWeight:'700',color:'#0f766e'}},displayName.charAt(0)));
      const camLbl=el('label',{style:{position:'absolute',bottom:'-.25rem',right:'-.25rem',width:'1.75rem',height:'1.75rem',background:'#0d9488',borderRadius:'9999px',display:'flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}});
      const camIco=ic('camera',13);camIco.style.color='#fff';camLbl.appendChild(camIco);
      const fIn=el('input',{type:'file',accept:'image/*',style:{display:'none'}});
      fIn.addEventListener('change',e=>{const f=e.target.files[0];if(!f)return;if(f.size>2*1024*1024){alert('Max 2 MB');return}const r=new FileReader();r.onload=async ev=>{dispatch({type:'UPDATE_USER',payload:{avatar:ev.target.result}});try{await Auth.updateProfile({avatar:ev.target.result})}catch(err){alert(err.message)}render()};r.readAsDataURL(f)});
      camLbl.appendChild(fIn);avWrap.append(avBox,camLbl);
      const avInfo=el('div');avInfo.append(el('p',{cls:'font-semibold text-lg',style:{color:s.darkMode?'#fff':'#0f172a'}},displayName),el('p',{cls:'text-sm text-slate-400'},authUser?.email||''));
      avCard.append(avWrap,avInfo);root.appendChild(avCard);
      // Info form card
      const infoCard=el('div',{cls:'card space-y-4 mb-4'});infoCard.appendChild(el('h3',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#0f172a'}},'Personal Information'));
      const grid=el('div',{cls:'grid g2 gap-4'});
      const nWrap=el('div');nWrap.append(el('label',{cls:'label'},'Full Name'));const nIn=el('input',{cls:'input',placeholder:'Your name'});nIn.value=nameVal;nIn.addEventListener('input',e=>{nameVal=e.target.value});nWrap.appendChild(nIn);
      const eWrap=el('div');eWrap.append(el('label',{cls:'label'},'Email'));const eIn=el('input',{type:'email',cls:'input',value:authUser?.email||'',readonly:'',style:{background:s.darkMode?'rgba(51,65,85,.5)':'#f8fafc',color:'#94a3b8',cursor:'not-allowed'}});eWrap.appendChild(eIn);
      const curWrap=el('div');curWrap.append(el('label',{cls:'label'},'Currency'));const cur=el('div',{cls:'input flex items-center gap-2',style:{background:s.darkMode?'rgba(51,65,85,.5)':'#f8fafc',cursor:'not-allowed'}});cur.append(el('span',{cls:'font-semibold'},'ETB'),el('span',{style:{color:'#94a3b8'}},'Ethiopian Birr'));curWrap.appendChild(cur);
      grid.append(nWrap,eWrap,curWrap);infoCard.appendChild(grid);
      const dmRow=el('div',{cls:'flex items-center justify-between p-3 rounded-xl',style:{background:s.darkMode?'rgba(51,65,85,.5)':'#f8fafc'}});
      const dmL=el('div',{cls:'flex items-center gap-2'});const dmIco=ic(s.darkMode?'moon':'sun',16);dmIco.style.color=s.darkMode?'#0d9488':'#f97316';dmL.append(dmIco,el('span',{cls:'text-sm font-medium',style:{color:s.darkMode?'#cbd5e1':'#334155'}},'Dark Mode'));
      const dmTog=Toggle(s.darkMode,()=>{dispatch({type:'TOGGLE_DARK'})});
      dmRow.append(dmL,dmTog);infoCard.appendChild(dmRow);
      const langRow=el('div',{cls:'flex items-center justify-between p-3 rounded-xl',style:{background:s.darkMode?'rgba(51,65,85,.5)':'#f8fafc'}});
      const langL=el('div',{cls:'flex items-center gap-2'});
      const langIco=ic('globe',16);langIco.style.color='#0d9488';
      langL.append(langIco,el('span',{cls:'text-sm font-medium',style:{color:s.darkMode?'#cbd5e1':'#334155'}},'Language'));
      const langSel=el('select',{cls:'input',style:{width:'auto',minWidth:'9rem',paddingTop:'.35rem',paddingBottom:'.35rem',fontSize:'.875rem'}});
      [['en','English'],['am','Amharic']].forEach(([code,label])=>{
        const o=el('option',{value:code},label);
        if((s.language||getLang())===code)o.selected=true;
        langSel.appendChild(o);
      });
      langSel.addEventListener('change',()=>{
        const code=langSel.value==='am'?'am':'en';
        localStorage.setItem(I18N_LANG_KEY,code);
        dispatch({type:'SET_LANGUAGE',payload:code});
        applyDocumentLang(code);
        renderApp();
      });
      langRow.append(langL,langSel);infoCard.appendChild(langRow);
      const saveBtn=el('button',{cls:'btn-primary'+(saved?' ':''),style:{background:saved?'#059669':'#0d9488'}},saved?'Saved!':'Save Changes');
      saveBtn.addEventListener('click',async()=>{dispatch({type:'UPDATE_USER',payload:{name:nameVal}});try{await Auth.updateProfile({name:nameVal});saved=true;render();setTimeout(()=>{saved=false;render()},2000)}catch(err){alert(err.message)}});
      infoCard.appendChild(saveBtn);root.appendChild(infoCard);
      // Logout
      const logCard=el('div',{cls:'card mb-4'});const logBtn=el('button',{cls:'btn-secondary w-full',style:{justifyContent:'center'}});logBtn.append(ic('log-out',16),' Logout');logBtn.addEventListener('click',async()=>{await Auth.logout();renderApp()});logCard.appendChild(logBtn);root.appendChild(logCard);
      // Danger zone
      const dangerCard=el('div',{cls:'card',style:{border:'1px solid '+(s.darkMode?'rgba(185,28,28,.5)':'#fee2e2')}});dangerCard.append(el('h3',{cls:'font-semibold mb-2',style:{color:'#dc2626'}},'Danger Zone'),el('p',{cls:'text-xs text-slate-400 mb-3'},'Permanently deletes all expenses, budgets, and settings.'));
      const clrBtn=el('button',{cls:'btn-danger'});clrBtn.append(ic('trash-2',14),' Clear All Data');
      clrBtn.addEventListener('click',()=>{
        // Custom confirm modal
        const bd=el('div',{cls:'modal-backdrop center'});
        const box=el('div',{cls:'modal-box anim-scale',style:{maxWidth:'24rem'}});
        const mHdr=el('div',{cls:'modal-header'});
        const warnIco=ic('alert-triangle',20);warnIco.style.color='#dc2626';
        mHdr.append(el('div',{cls:'flex items-center gap-2'},[warnIco,el('h2',{cls:'font-bold text-lg',style:{color:'#dc2626'}},'Clear All Data')]));
        const xBtn=el('button',{cls:'icon-btn'});xBtn.appendChild(ic('x',18));xBtn.addEventListener('click',()=>bd.remove());mHdr.appendChild(xBtn);
        box.appendChild(mHdr);
        const body=el('div',{style:{padding:'1.25rem'}});
        body.append(
          el('p',{cls:'text-sm mb-2',style:{color:s.darkMode?'#cbd5e1':'#334155'}},'This will permanently delete:'),
          el('ul',{style:{listStyle:'disc',paddingLeft:'1.25rem',color:'#64748b',fontSize:'.875rem',lineHeight:'1.75'}},
            [el('li',{},'All expenses'),el('li',{},'All budgets'),el('li',{},'All bills & recurring entries'),el('li',{},'All app settings')]
          ),
          el('p',{cls:'text-sm mt-3',style:{color:s.darkMode?'#fca5a5':'#dc2626',fontWeight:'600'},'data-role':'warn'},'This action cannot be undone.')
        );
        box.appendChild(body);
        const footer=el('div',{cls:'modal-footer'});
        const cancelBtn=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},'Cancel');cancelBtn.addEventListener('click',()=>bd.remove());
        const confirmBtn=el('button',{style:{flex:1,display:'flex',alignItems:'center',justifyContent:'center',gap:'.5rem',background:'#dc2626',color:'#fff',fontWeight:'600',padding:'.5rem 1rem',borderRadius:'.75rem',border:'none',cursor:'pointer',fontSize:'.875rem'}});
        confirmBtn.append(ic('trash-2',14),' Delete Everything');
        confirmBtn.addEventListener('click',async()=>{bd.remove();try{await clearAllData();renderApp()}catch(err){alert(err.message)}});
        footer.append(cancelBtn,confirmBtn);box.appendChild(footer);
        bd.appendChild(box);bd.addEventListener('click',e=>{if(e.target===bd)bd.remove()});
        document.getElementById('app-root').appendChild(bd);renderIcons(bd);
      });
      dangerCard.appendChild(clrBtn);root.appendChild(dangerCard);
    }else if(tab==='security'){
      const secCard=el('div',{cls:'card space-y-4'});secCard.append(el('h3',{cls:'font-semibold',style:{color:s.darkMode?'#fff':'#0f172a'}},'Password Reset'),el('p',{cls:'text-sm text-slate-400'},'Reset your password via OTP code sent to your email.'));
      if(authUser?.provider==='google'&&!authUser?.hasPassword){
        secCard.appendChild(el('div',{style:{background:'rgba(59,130,246,.1)',borderRadius:'.75rem',padding:'1rem'}},el('p',{style:{fontSize:'.875rem',color:'#3b82f6'}},'This Google account can create an app password by OTP while still keeping Google Sign-In.')));
      }
      if(fErr)secCard.appendChild(el('div',{style:{background:'rgba(239,68,68,.15)',border:'1px solid rgba(239,68,68,.3)',borderRadius:'.75rem',padding:'.75rem',fontSize:'.875rem',color:'#fca5a5'}},fErr));
      if(!showReset){const btn=el('button',{cls:'btn-primary'});btn.append(ic('lock',14),' Reset Password via OTP');btn.addEventListener('click',()=>{showReset=true;fStep='send';fOtp='';fOtpInput='';render()});secCard.appendChild(btn)}
      else if(fStep==='send'){
        const btn=el('button',{cls:'btn-primary'});btn.append(ic('send',14),' Send OTP to Email');
        btn.addEventListener('click',async()=>{fErr='';try{const otpMeta=await Auth.generateOtp(fEmail);fOtp=otpMeta.message||('We sent a 6-digit code to '+(otpMeta.maskedEmail||fEmail)+'.');fStep='verify';fTimer=60;render();startSecurityTimer()}catch(e){fErr=e.message;render()}});secCard.appendChild(btn);
      }else if(fStep==='verify'){
        if(fOtp){secCard.appendChild(el('div',{style:{background:'rgba(59,130,246,.15)',border:'1px solid rgba(59,130,246,.3)',borderRadius:'.75rem',padding:'1rem',fontSize:'.875rem',color:'#3b82f6'}},fOtp))}
        const oIn=el('input',{type:'text',inputmode:'numeric',maxlength:'6',cls:'input',placeholder:'6-digit code',style:{textAlign:'center',fontSize:'1.25rem',fontFamily:'monospace',letterSpacing:'.1em'}});oIn.value=fOtpInput;oIn.addEventListener('input',e=>fOtpInput=e.target.value.replace(/\D/g,''));
        const row=el('div',{cls:'flex gap-2'});
        const vBtn=el('button',{cls:'btn-primary flex-1',style:{justifyContent:'center'}},'Verify Code');vBtn.addEventListener('click',async()=>{fErr='';try{await Auth.verifyOtp(fEmail,fOtpInput.trim());fStep='newpw';render()}catch(e){fErr=e.message;render()}});
        const rBtn=el('button',{cls:'btn-secondary flex-1',style:{justifyContent:'center'}},fTimer>0?fTimer+'s':'Resend');securityTimerButtons.push(rBtn);syncSecurityTimerButtons();rBtn.addEventListener('click',async()=>{try{const otpMeta=await Auth.generateOtp(fEmail);fOtp=otpMeta.message||('We sent a 6-digit code to '+(otpMeta.maskedEmail||fEmail)+'.');fTimer=60;render();startSecurityTimer()}catch(e){fErr=e.message;render()}});
        row.append(vBtn,rBtn);secCard.append(oIn,row);
      }else if(fStep==='newpw'){
        const pw1=el('input',{type:'password',cls:'input',placeholder:'New password (min 8 chars)'});pw1.value=fNewPw;pw1.addEventListener('input',e=>fNewPw=e.target.value);
        const pw2=el('input',{type:'password',cls:'input',placeholder:'Confirm new password'});pw2.value=fConfPw;pw2.addEventListener('input',e=>fConfPw=e.target.value);
        const btn=el('button',{cls:'btn-primary'},'Set New Password');btn.addEventListener('click',async()=>{fErr='';if(fNewPw.length<8){fErr='Min 8 characters.';render();return}if(fNewPw!==fConfPw){fErr='Passwords do not match.';render();return}try{await Auth.resetPassword(fEmail,fNewPw);fStep='done';render()}catch(e){fErr=e.message;render()}});
        secCard.append(pw1,pw2,btn);
      }else{
        const doneIco=ic('check-circle',28);doneIco.style.color='#059669';
        const doneBtn=el('button',{style:{color:'#0d9488',background:'none',border:'none',cursor:'pointer',fontSize:'.875rem',marginTop:'.25rem'}},'Done');
        doneBtn.addEventListener('click',()=>{showReset=false;fStep='send';fOtp='';fOtpInput='';fErr='';render()});
        secCard.appendChild(el('div',{style:{background:'#ecfdf5',borderRadius:'.75rem',padding:'1rem',display:'flex',alignItems:'center',gap:'.75rem'}},[doneIco,el('div',{},[el('p',{cls:'text-sm font-semibold',style:{color:'#059669'}},'Password updated!'),doneBtn])]));
      }
      root.appendChild(secCard);
    }else if(tab==='receipts'){
      const receipts=s.expenses.filter(e=>e.receipt);
      root.appendChild(el('p',{cls:'text-sm text-slate-400 mb-4'},receipts.length+' receipt'+(receipts.length!==1?'s':'')+' stored'));
      if(!receipts.length){root.appendChild(el('div',{cls:'empty-state card'},[ic('image',32),el('p',{cls:'font-medium text-slate-500 mt-2'},'No receipts uploaded yet')]));renderIcons(root);return}
      const grid=el('div',{cls:'grid g3 gap-3'});
      receipts.forEach(exp=>{
        const card=el('div',{cls:'card p-0 overflow-hidden'});
        const img=el('img',{src:exp.receipt,alt:'Receipt',style:{width:'100%',height:'8rem',objectFit:'cover',cursor:'pointer'}});
        function openReceiptTab(dataUrl){
          try{
            const arr=dataUrl.split(',');const mime=(arr[0].match(/:(.*?);/)||[])[1]||'image/png';
            const bstr=atob(arr[1]);let n=bstr.length;const u8=new Uint8Array(n);while(n--)u8[n]=bstr.charCodeAt(n);
            const blob=new Blob([u8],{type:mime});const blobUrl=URL.createObjectURL(blob);
            const win=window.open('','_blank','noopener');
            if(win){win.document.write('<html><body style="margin:0;background:#000;display:flex;align-items:center;justify-content:center;min-height:100vh"><img src="'+blobUrl+'" style="max-width:100%;max-height:100vh;object-fit:contain"></body></html>');win.document.close();}
          }catch(err){window.open(dataUrl,'_blank','noopener');}
        }
        img.addEventListener('click',()=>openReceiptTab(exp.receipt));
        const info=el('div',{style:{padding:'.75rem'}});
        info.append(el('p',{cls:'text-xs font-semibold truncate',style:{color:s.darkMode?'#cbd5e1':'#334155'}},exp.note||exp.category),el('p',{cls:'text-xs text-slate-400'},formatETB(exp.amount)+' · '+exp.date));
        const acts=el('div',{cls:'flex gap-1 mt-2 flex-wrap'});
        const viewBtn=el('button',{cls:'btn-secondary',style:{padding:'.25rem .5rem',fontSize:'.7rem'}});viewBtn.append(ic('external-link',12),' View');viewBtn.addEventListener('click',()=>openReceiptTab(exp.receipt));
        const editBtn=el('button',{cls:'btn-secondary',style:{padding:'.25rem .5rem',fontSize:'.7rem'}});editBtn.append(ic('edit-2',12),' Edit');editBtn.addEventListener('click',()=>renderExpModal(exp));
        const dlBtn=el('button',{cls:'btn-danger',style:{padding:'.25rem .5rem',fontSize:'.7rem'}});dlBtn.append(ic('trash-2',12),' Remove');dlBtn.addEventListener('click',()=>{if(confirm('Remove receipt image from this expense?'))dispatch({type:'UPDATE_EXPENSE',payload:{...exp,receipt:null}})});
        acts.append(viewBtn,editBtn,dlBtn);info.appendChild(acts);card.append(img,info);grid.appendChild(card);
      });
      root.appendChild(grid);
    }else if(tab==='notifications'){
      const nCard=el('div',{cls:'card'});
      nCard.append(el('h3',{cls:'font-semibold mb-1',style:{color:s.darkMode?'#fff':'#0f172a'}},'Email Notifications'),el('p',{cls:'text-xs text-slate-400 mb-4'},'When enabled, you receive bill reminders, budget alerts, recurring reminders, weekly summaries, and monthly reports by email.'));
      const row=el('div',{cls:'flex items-center justify-between p-3 rounded-xl',style:{background:s.darkMode?'rgba(51,65,85,.5)':'#f8fafc'}});
      const lft=el('div',{cls:'flex items-center gap-2'});const mailIco=ic('mail',16);mailIco.style.color='#0d9488';lft.append(mailIco,el('span',{cls:'text-sm font-medium',style:{color:s.darkMode?'#cbd5e1':'#334155'}},'Email notifications'));
      const tog=Toggle(emailNotificationsEnabled(s),v=>{profileTab='notifications';setEmailNotifications(v)});
      row.append(lft,tog);nCard.appendChild(row);root.appendChild(nCard);
    }
    renderIcons(root);
  }
  // Polyfill: .tap() for chaining
  if(!HTMLElement.prototype.tap)HTMLElement.prototype.tap=function(fn){fn(this);return this};
  render();
}

// â•â•â•â•â•â• MAIN â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function applyTheme(dark){document.documentElement.classList.toggle('dark',!!dark)}
function renderApp(){
  applyTheme(S.darkMode);
  if(!Auth.session()){renderLogin();return}
  if(PAGE==='groups')PAGE='dashboard';
  renderLayout();renderPage();
}

sub(newS=>{
  applyTheme(newS.darkMode);
  applyDocumentLang(newS.language);
  renderTopbar();renderSidebar();renderBottomNav();
  if(PAGE==='profile')pgProfile(document.getElementById('page'));
  else renderPage();
});

// ══════════════════ AI CHAT WIDGET ══════════════════════════════════════════
(function(){
  // ── State ────────────────────────────────────────────────────────────────
  let open=false, sending=false;
  const messages=[];   // {role:'user'|'ai', text:string, time:string}
  let unread=0;

  function nowTime(){
    return new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  }

  // ── DOM creation (once) ──────────────────────────────────────────────────
  function buildWidget(){
    // FAB button
    const fab=document.createElement('button');
    fab.id='ai-fab';
    fab.setAttribute('aria-label','Open AI financial advisor');
    fab.innerHTML=`
      <span class="ai-fab-icon" style="line-height:0">${svgSparkle()}</span>
      <span class="ai-fab-icon ai-fab-close" style="line-height:0">${svgX()}</span>
      <span id="ai-badge" class="hidden">0</span>`;
    fab.addEventListener('click',togglePanel);

    // Panel
    const panel=document.createElement('div');
    panel.id='ai-panel';
    panel.classList.add('ai-hidden');
    panel.innerHTML=`
      <div id="ai-header">
        <div id="ai-header-icon">${svgBot()}</div>
        <div id="ai-header-text">
          <div id="ai-header-title">SpendWise AI</div>
          <div id="ai-header-sub" id="ai-status">Your financial advisor</div>
        </div>
        <button id="ai-clear-btn" title="Clear conversation">${svgTrash()}</button>
      </div>
      <div id="ai-messages"></div>
      <div id="ai-footer">
        <textarea id="ai-input" placeholder="Ask about your finances…" rows="1"></textarea>
        <button id="ai-send" disabled aria-label="Send">${svgSend()}</button>
      </div>`;

    document.body.appendChild(fab);
    document.body.appendChild(panel);

    // Wire events
    const input=document.getElementById('ai-input');
    const sendBtn=document.getElementById('ai-send');

    input.addEventListener('input',()=>{
      sendBtn.disabled=input.value.trim()===''||sending;
      // Auto-resize textarea
      input.style.height='auto';
      input.style.height=Math.min(input.scrollHeight,112)+'px';
    });
    input.addEventListener('keydown',e=>{
      if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}
    });
    sendBtn.addEventListener('click',sendMessage);
    document.getElementById('ai-clear-btn').addEventListener('click',clearChat);

    renderMessages();
  }

  // ── SVG icons (inline, no external dep) ─────────────────────────────────
  function svgSparkle(){
    return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5z"/>
      <path d="M5 19l.75 2.25L8 22l-2.25.75L5 25l-.75-2.25L2 22l2.25-.75z" opacity=".6"/></svg>`;}
  function svgX(){
    return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;}
  function svgBot(){
    return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/>
      <line x1="12" y1="7" x2="12" y2="11"/><line x1="8" y1="16" x2="8" y2="16" stroke-width="3"/>
      <line x1="16" y1="16" x2="16" y2="16" stroke-width="3"/></svg>`;}
  function svgSend(){
    return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;}
  function svgTrash(){
    return `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
      <path d="M9 6V4h6v2"/></svg>`;}

  // ── Toggle open/close ────────────────────────────────────────────────────
  function togglePanel(){
    open=!open;
    const fab=document.getElementById('ai-fab');
    const panel=document.getElementById('ai-panel');
    fab.classList.toggle('open',open);

    if(open){
      unread=0;
      updateBadge();
      panel.classList.remove('ai-hidden','ai-closing');
      panel.classList.add('ai-opening');
      panel.addEventListener('animationend',()=>panel.classList.remove('ai-opening'),{once:true});
      setTimeout(()=>document.getElementById('ai-input')?.focus(),240);
      scrollToBottom();
    }else{
      panel.classList.remove('ai-opening');
      panel.classList.add('ai-closing');
      panel.addEventListener('animationend',()=>{
        panel.classList.add('ai-hidden');
        panel.classList.remove('ai-closing');
      },{once:true});
    }
  }

  function updateBadge(){
    const b=document.getElementById('ai-badge');
    if(!b)return;
    if(unread>0&&!open){b.textContent=unread>9?'9+':String(unread);b.classList.remove('hidden');}
    else b.classList.add('hidden');
  }

  // ── Messages rendering ───────────────────────────────────────────────────
  function renderMessages(){
    const box=document.getElementById('ai-messages');
    if(!box)return;
    box.innerHTML='';

    if(messages.length===0){
      const s=getState();
      const name=s.user?.name?.split(' ')[0]||'there';
      box.innerHTML=`<div id="ai-empty">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <div style="font-weight:600;color:var(--ai-empty-title,#475569)">Hi ${escHtml(name)}! 👋</div>
        <div>Ask me anything about your spending, budgets, or bills.</div>
      </div>`;
      return;
    }

    messages.forEach(m=>{
      const wrap=document.createElement('div');
      wrap.className='ai-msg '+(m.role==='user'?'user':'ai');
      const bubble=document.createElement('div');
      bubble.className='ai-bubble';
      bubble.innerHTML=formatAiText(m.text);
      const ts=document.createElement('div');
      ts.className='ai-timestamp';
      ts.textContent=m.time;
      wrap.append(bubble,ts);
      box.appendChild(wrap);
    });
    scrollToBottom();
  }

  function addTypingIndicator(){
    const box=document.getElementById('ai-messages');
    if(!box)return;
    const wrap=document.createElement('div');
    wrap.className='ai-msg ai ai-typing';
    wrap.id='ai-typing-indicator';
    wrap.innerHTML=`<div class="ai-bubble"><span class="ai-typing-dot"></span><span class="ai-typing-dot"></span><span class="ai-typing-dot"></span></div>`;
    box.appendChild(wrap);
    scrollToBottom();
  }

  function removeTypingIndicator(){
    document.getElementById('ai-typing-indicator')?.remove();
  }

  function scrollToBottom(){
    const box=document.getElementById('ai-messages');
    if(box)setTimeout(()=>{box.scrollTop=box.scrollHeight;},30);
  }

  // Format AI text: bold, line breaks
  function formatAiText(text){
    return escHtml(text)
      .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
      .replace(/\n/g,'<br>');
  }

  function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Send message ─────────────────────────────────────────────────────────
  async function sendMessage(){
    const input=document.getElementById('ai-input');
    const sendBtn=document.getElementById('ai-send');
    if(!input)return;
    const text=input.value.trim();
    if(!text||sending)return;

    sending=true;
    sendBtn.disabled=true;
    input.value='';
    input.style.height='auto';

    messages.push({role:'user',text,time:nowTime()});
    renderMessages();
    addTypingIndicator();
    setStatus('Thinking…');

    try{
      const res=await fetch('ai.php?action=chat',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:text}),
      });
      const data=await res.json();
      removeTypingIndicator();

      if(data.ok&&data.data?.reply){
        messages.push({role:'ai',text:data.data.reply,time:nowTime()});
        if(!open){unread++;updateBadge();}
      }else{
        messages.push({role:'ai',text:'Sorry, something went wrong. Please try again.',time:nowTime()});
      }
    }catch(e){
      removeTypingIndicator();
      messages.push({role:'ai',text:'Network error. Please check your connection.',time:nowTime()});
    }finally{
      sending=false;
      sendBtn.disabled=input.value.trim()==='';
      setStatus('Your financial advisor');
      renderMessages();
    }
  }

  // ── Clear conversation ───────────────────────────────────────────────────
  async function clearChat(){
    messages.length=0;
    renderMessages();
    try{await fetch('ai.php?action=clear',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});}
    catch(e){}
  }

  function setStatus(text){
    const el=document.getElementById('ai-header-sub');
    if(el)el.textContent=text;
  }

  // ── Boot: only show widget when logged in ───────────────────────────────
  if(Auth.session())buildWidget();
})();

renderApp();
