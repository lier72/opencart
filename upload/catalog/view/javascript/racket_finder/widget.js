/* Racket Finder widget. Generated from finder/v2/script.js by scratchpad/mkwidget.py — edit the prototype, not this file. */
const RF=(typeof window!=="undefined"&&window.RF)||{};
const DATA={items:RF.items||[],fit:RF.fit||{sw:[0,0,0],h:[0,0,0]}};
const TECH=RF.tech||[];
// ---- язык: ?lang=ru|en → язык страницы (на сайте фиксирован) → выбор посетителя → язык браузера
const PAGE_LANG=RF.lang||'auto',ALT_URL=RF.alt||{ru:'',en:''};
let LANG=(()=>{let q=null;try{q=new URLSearchParams(location.search).get('lang')}catch(e){}if(q==='ru'||q==='en')return q;if(PAGE_LANG==='ru'||PAGE_LANG==='en')return PAGE_LANG;try{const s=localStorage.getItem('podbor_lang');if(s==='ru'||s==='en')return s}catch(e){}return /^(ru|be|kk|ky)\b/i.test((typeof navigator!=='undefined'&&navigator.language)||'')?'ru':'en'})();
const T=(ru,en)=>LANG==='en'?en:ru;
if(typeof document!=='undefined')document.documentElement.lang=LANG;
const UW={2:92,3:87,4:82,5:77,6:72,7:67,8:63};
const SER={AXForce:{p:1,s:0,c:0,a:.3},Astrox:{p:1,s:0,c:0,a:.3},Tectonic:{p:.5,s:0,c:.3,a:.3},Duora:{p:.3,s:.3,c:.5,a:.5},
 BladeX:{p:-.5,s:1,c:0,a:1},Nanoflare:{p:-.5,s:1,c:0,a:1},Aeronaut:{p:.5,s:.5,c:0,a:1},Calibar:{p:.5,s:.3,c:0,a:.8},
 Halbertec:{p:0,s:0,c:1,a:.6},Voltric:{p:1,s:0,c:0,a:.3},Nanoray:{p:-.5,s:1,c:0,a:1},Arcsaber:{p:0,s:0,c:1,a:.6},ArcSaber:{p:0,s:0,c:1,a:.6},WindStorm:{p:-.3,s:.6,c:0,a:.6},Windstorm:{p:-.3,s:.6,c:0,a:.6},TurboCharging:{p:0,s:.6,c:0,a:.5},AirStream:{p:.3,s:.5,c:0,a:.8}};
const FLEX_RU=['','гибкий','средне-гибкий','средний','средне-жёсткий','жёсткий','очень жёсткий'],FLEX_EN=['','flexible','medium-flexible','medium','medium-stiff','stiff','extra stiff'];
const FX=i=>(LANG==='en'?FLEX_EN:FLEX_RU)[i];
const cap1=s=>s.charAt(0).toUpperCase()+s.slice(1);
const flexTxt=o=>o.f?T(FX(o.f)+' стержень',FX(o.f)+' shaft'):T('жёсткость стержня не указана производителем','shaft stiffness not stated by the manufacturer'); // неизвестная жёсткость — не «средняя»
const AXK=['power','speed','control','ease','comfort'];
const AXL={power:['Мощь','Power'],speed:['Скорость','Speed'],control:['Контроль','Control'],ease:['Прощает','Forgiving'],comfort:['Комфорт','Comfort']};
const AXN=()=>AXK.map(k=>[k,T(...AXL[k])]);
const clamp=v=>Math.max(0,Math.min(1,v));
const num1=v=>String(Math.round(v*10)/10).replace('.',LANG==='en'?'.':',');
// ---- items: measured data first, card data as fallback
const FIT=DATA.fit;
const LIVE=RF.live||{};DATA.items.forEach(o=>{const l=LIVE[o.id];if(l){o.p=l.p;o.q=l.q;}});
const R=DATA.items.map((o,i)=>{
  const W=o.mw||UW[o.u]||80;
  const SW=o.sw||(FIT.sw[0]+FIT.sw[1]*W+FIT.sw[2]*o.bp);
  const H=o.hd||(FIT.h[0]+FIT.h[1]*W+FIT.h[2]*o.bp);
  const tw=/требует проверки/.test(o.tl||'')?.6:1;
  const sum=[0,0,0,0,0];
  (o.t||[]).forEach(ti=>{let m=tw;if(/^SHARP WIND/.test(TECH[ti].n)&&o.sharp)m*=o.sharp/100;TECH[ti].e.forEach((v,j)=>sum[j]+=v*m)});
  return {...o,uid:o.id?o.id+'-'+o.u:'r'+i, // один товар на сайте может быть в нескольких весах (Arcsaber 11 Tour 3U/4U) — uid по товару и весу
    W,SW,H,swEst:!o.sw,tsum:sum,hasT:(o.t||[]).length>0,bp:Math.round(o.bp)};
});
const span=a=>[Math.min(...a),Math.max(...a)];
// нормировка — только по нашей базе (сайт + замеры); ракетки, добавленные из каталогов, её не сдвигают
const R0=R.filter(o=>!o.cat);
const [swMn,swMx]=span(R0.map(o=>o.SW)),[hMn,hMx]=span(R0.map(o=>o.H));
const Dz=x=>.5+.5*Math.tanh(x/2.5);
function design(o){
  if(o.hasT)return o.tsum.map(Dz);
  const sp=SER[o.s]||{p:0,s:0,c:0,a:.5};
  return [(sp.p+1)/2,.6*(sp.s+1)/2+.4*sp.a,(sp.c+1)/2,.5,.5];
}
function rawAx(o){
  const swN=clamp((o.SW-swMn)/(swMx-swMn)),hN=clamp((o.H-hMn)/(hMx-hMn));
  const f=o.f||3;const stiff=o.fe?(.5*(f-1)/5+.5*(o.fe-1)/4):(f-1)/5;
  const [Dp,Ds,Dc,De,Dk]=design(o);o.D=[Dp,Ds,Dc,De,Dk];
  const thin=o.d?clamp((7.0-o.d)/1.0):.3;
  const Wn=clamp((o.W-63)/(90-63));
  const lvE={1:1,2:.67,3:.33,4:0}[o.lv]??.5;
  return {
    power:.5*swN+.1*hN+.1*stiff+.3*Dp,
    speed:.5*(1-swN)+.1*thin+.4*Ds,
    control:.3*Wn+.15*clamp(1-Math.abs(o.bp-295)/25)+.15*stiff+.4*Dc,
    ease:.4*(1-stiff)+.2*(1-Wn)+.1*lvE+.3*De,
    comfort:.3*(1-stiff)+.15*(1-Wn)+.55*Dk
  };
}
R.forEach(o=>{o.ax=rawAx(o)});
const RANGE={};AXK.forEach(k=>{RANGE[k]=span(R0.map(o=>o.ax[k]))});
const stretch=a=>{const r={};for(const k in a){const[mn,mx]=RANGE[k];r[k]=Math.max(1,Math.min(10,1+8.5*(a[k]-mn)/(mx-mn)))}return r};
R.forEach(o=>{o.ax=stretch(o.ax);if(o.cor){o.auto={...o.ax};AXK.forEach((k,j)=>{o.ax[k]=Math.max(1,Math.min(10,o.ax[k]+(o.cor[j]||0)))})}});
// ---- характер серии: топовая модель серии получает 10 по главной оси.
// У Li-Ning буква в названии меняет направление модели: B Boost — универсал/контроль, C Combat — мощь, D Drive — скорость, I Instinct — лёгкая и быстрая.
const SERIES={
 'Li-Ning|AXForce':{ax:'power',top:['AXForce 100'],pin:{'AXForce 100 II':9.5},rel:[['AXForce 100 II','control','AXForce 100',.5]]},
 'Li-Ning|BladeX':{ax:'speed',top:['BladeX 900','BladeX 900 MAX Blue']}, // 900 MAX Gold (Sun) на диаграмме Li-Ning мягче и тяжелее — не эталон скорости
 'Li-Ning|Halbertec':{ax:'control',top:['Halbertec 9000'],pin:{'Halbertec 9000 Power':9.5},rel:[['Halbertec 9000 Power','power','Halbertec 9000',.5]]},
 // прошлые серии: 10 только у флагмана своей классификации; буквенные версии «чужого» направления — не выше 8; Instinct — просто лёгкие, без эталонов
 'Li-Ning|Tectonic':{ax:'power',top:['Tectonic 9'],letters:true},
 'Li-Ning|Aeronaut':{ax:'control',top:['Aeronaut 9000','Aeronaut 9000C','Aeronaut 9000D'],letters:true},
 'Li-Ning|Calibar':{ax:'speed',top:['Calibar 900','Calibar 900B','Calibar 900C'],letters:true},
 'Li-Ning|TurboCharging':{ax:'speed',top:[],letters:true},
 'Li-Ning|WindStorm':{ax:'control',top:[]},
 'Yonex|Astrox':{ax:'power',top:['Astrox 99 Pro','Astrox 100ZZ','Astrox 100VA ZZ']},
 'Yonex|Nanoflare':{ax:'speed',top:['Nanoflare 1000 Z','Nanoflare 800 Pro']},
 // Arcsaber 7 — мягче стержень (Medium против Stiff у 11), на диаграмме Yonex мягче и чуть легче в голове: меньше контроля, больше скорости
 'Yonex|Arcsaber':{ax:'control',top:['Arcsaber 11 Pro','Arcsaber 11'],gap:[['Arcsaber 7 Pro','speed','Arcsaber 11 Pro',.8],['Arcsaber 7 Pro','control','Arcsaber 11 Pro',-1],['Arcsaber 7 Pro','power','Arcsaber 11 Pro',-.5],
   ['Arcsaber 7 Tour','speed','Arcsaber 11 Tour',.8],['Arcsaber 7 Tour','control','Arcsaber 11 Tour',-1],['Arcsaber 7 Play','speed','Arcsaber 11 Play',.5],['Arcsaber 7 Play','control','Arcsaber 11 Play',-.6]]},
 'Yonex|Duora':{ax:'control',top:['Duora Z-Strike','Duora 10']},
 // Victor: Thruster — атака, DriveX — универсал/контроль, Auraspeed — скорость (только справочные ракетки)
 // топы Victor: Thruster F C Ultra и Ryuga III; Auraspeed 100X Ultra и Auraspeed 99; DriveX 12 (Thruster F Ultra — другая модель, не топ; в базе пока есть только DriveX 12 из топов)
 'Victor|Thruster':{ax:'power',top:['Thruster F C Ultra','Thruster Ryuga III']},
 'Victor|DriveX':{ax:'control',top:['DriveX 12']},
 'Victor|AuraSpeed':{ax:'speed',top:['Auraspeed 100X Ultra','Auraspeed 99']},
};
const LETTER={B:'control',C:'power',D:'speed'},LETTER_N={B:'Boost',C:'Combat',D:'Drive',I:'Instinct'},LETTER_TOP=8;
const letterOf=(o,S)=>{if(!S.letters)return null;const m=o.m.match(/\d([BCDI])$/);return m?m[1]:null};
const LVW={4:1,3:.5,2:.15,1:0,0:0},SER_CAP=9.3;
for(const [key,S] of Object.entries(SERIES)){
  const mem=R.filter(o=>o.b+'|'+o.s===key);if(!mem.length)continue;
  mem.forEach(o=>{o.ser0=o.ser0||{...o.ax};const L=letterOf(o,S);o.sAx=(L&&LETTER[L])||S.ax;o.sLetter=L?LETTER_N[L]:null;o.sKey=key});
  const tops=S.top.filter(m=>mem.some(o=>o.m===m));if(!tops.length)continue;
  const pin=S.pin||{};
  // ориентир модели: для мощи — самый тяжёлый вес (3U мощнее 4U), для остальных осей — лучший вес
  const best=(m,k)=>{const v=mem.filter(o=>o.m===m);if(k==='power'){const h=Math.min(...v.map(o=>o.u));return v.find(o=>o.u===h).ser0[k]}return Math.max(...v.map(o=>o.ser0[k]))};
  const fAx={};tops.forEach(m=>{const k=mem.find(o=>o.m===m).sAx;fAx[k]=Math.min(fAx[k]??9e9,(mem.find(o=>o.m===m).sAx===S.ax?10:LETTER_TOP)/best(m,k))});
  const fSer=fAx[S.ax]??Math.min(...Object.values(fAx));
  // 1) топовые модели: ориентир = 10, остальные веса пропорционально
  const topV=m=>{const o=mem.find(x=>x.m===m);return o.sAx===S.ax?10:LETTER_TOP};
  mem.filter(o=>tops.includes(o.m)).forEach(o=>{const k=o.sAx;o.ax[k]=Math.min(10,o.ser0[k]*topV(o.m)/best(o.m,k));o.serTop=true});
  const topU=(k,u)=>{const t=mem.filter(o=>tops.includes(o.m)&&o.sAx===k&&o.u===u);return t.length?Math.max(...t.map(o=>o.ax[k])):null};
  // 2) закреплённые: на столько ниже топовой модели того же веса
  mem.filter(o=>pin[o.m]!=null).forEach(o=>{const k=o.sAx,tu=topU(k,o.u);o.ax[k]=tu!=null?tu-(10-pin[o.m]):Math.min(10,o.ser0[k]*pin[o.m]/best(o.m,k));o.serTop=true});
  // 3) остальные: подтягиваются к топу по уровню, но ниже топовой и закреплённой модели того же веса
  mem.forEach(o=>{
    if(tops.includes(o.m)||pin[o.m]!=null)return;
    const k=o.sAx,tu=topU(k,o.u),f=fAx[k]??fSer;
    const pu=mem.filter(x=>pin[x.m]!=null&&x.sAx===k&&x.u===o.u).map(x=>x.ax[k]-.2);
    o.ax[k]=Math.max(1,Math.min(SER_CAP,k===S.ax?10:LETTER_TOP,tu!=null?tu-.3:10,...pu,o.ser0[k]*(1+(f-1)*(LVW[o.lv]??0))));
  });
  // gap: модель ставится на заданном расстоянии от соседней модели серии в том же весе (например, Arcsaber 7 — чуть быстрее и чуть менее контрольная, чем 11)
  (S.gap||[]).forEach(([m,ax2,over,d])=>mem.filter(o=>o.m===m).forEach(o=>{const ref=mem.find(x=>x.m===over&&x.u===o.u)||mem.find(x=>x.m===over);if(!ref)return;o.ax[ax2]=Math.max(1,Math.min(10,ref.ax[ax2]+d))}));
  (S.rel||[]).forEach(([m,ax2,over,d])=>mem.filter(o=>o.m===m).forEach(o=>{const ref=mem.find(x=>x.m===over&&x.u===o.u)||mem.find(x=>x.m===over);if(ref&&o.ax[ax2]<=ref.ax[ax2])o.ax[ax2]=Math.min(10,ref.ax[ax2]+d)}));
}
// ---- Instinct — просто лёгкая серия: ни одна ось не 10
R.forEach(o=>{if(o.sLetter==='Instinct')AXK.forEach(k=>{if(o.ax[k]>9){o.ser0=o.ser0||{...o.ax};o.ax[k]=9}})});
// ---- внутри одной модели более тяжёлая ракетка всегда мощнее: 3U > 4U > 5U
{const g={};R.forEach(o=>{(g[o.b+'|'+o.m]=g[o.b+'|'+o.m]||[]).push(o)});
 Object.values(g).forEach(v=>{if(v.length<2)return;v.sort((a,b)=>a.u-b.u);for(let i=1;i<v.length;i++){if(v[i].u>v[i-1].u&&v[i].ax.power>v[i-1].ax.power-.3){
   // если тяжёлый вес — только из каталога, поднимаем его, а не опускаем ракетку из магазина
   if(v[i-1].cat&&!v[i].cat){v[i-1].ax.power=Math.min(10,v[i].ax.power+.3);continue}
   v[i].ser0=v[i].ser0||{...v[i].ax};v[i].ax.power=Math.max(1,v[i-1].ax.power-.3)}}})}
// ---- и наоборот: более лёгкий вес той же модели не медленнее тяжёлого (5U ≥ 4U ≥ 3U по скорости)
{const g={};R.forEach(o=>{(g[o.b+'|'+o.m]=g[o.b+'|'+o.m]||[]).push(o)});
 Object.values(g).forEach(v=>{if(v.length<2)return;v.sort((a,b)=>a.u-b.u);for(let i=1;i<v.length;i++){if(v[i].u>v[i-1].u&&v[i].ax.speed<v[i-1].ax.speed+.2){
   // лёгкий вес только из каталога — поднимаем его; иначе опускаем тяжёлый
   if(v[i].cat&&!v[i-1].cat){v[i].ax.speed=Math.min(10,v[i-1].ax.speed+.2);continue}
   v[i-1].ser0=v[i-1].ser0||{...v[i-1].ax};v[i-1].ax.speed=Math.max(1,v[i].ax.speed-.2)}}})}
// ---- материал обода: алюминиевый обод не даёт мощи, сколько бы ни весила голова
R.forEach(o=>{if(o.frame==='алюминий'){o.ser0=o.ser0||{...o.ax};o.ax.power=Math.min(o.ax.power,3.5)}});
// ---- уровень ракетки (решение магазина 6 ноября 2026): коридоры, которые не перекрываются у топа и начального уровня.
// Мощь, скорость и контроль: начальная 1–5, любительская 2,5–6,5, полупрофессиональная 4–8,5, профессиональная 5,5–10. Внутри коридора — по замерам и технологиям;
// топовая ракетка никогда не слабее начальной ни по одной из трёх осей.
const LV_BAND={1:[1,5],2:[2.5,6.5],3:[4,8.5],4:[5.5,10]},LV_RU={1:'начальная',2:'любительская',3:'полупрофессиональная',4:'профессиональная'},LV_EN={1:'entry-level',2:'recreational',3:'semi-pro',4:'professional'};
const LVN=lv=>(LANG==='en'?LV_EN:LV_RU)[lv]||'';
const band=(v,b)=>b[0]+(v-1)*(b[1]-b[0])/9;
R.forEach(o=>{const b=LV_BAND[o.lv];if(!b)return;o.ser0=o.ser0||{...o.ax};['power','speed','control'].forEach(k=>{o.ax[k]=band(o.ax[k],b)})});
// ---- Yonex как на диаграмме Yonex 2026 (решение магазина 6 ноября 2026): в одном классе и весе Arcsaber — между силовыми Astrox (100, 99, 88D)
// и Nanoflare: быстрее любой силовой Astrox и медленнее любой Nanoflare; по мощи — между ними (ниже средней у силовых Astrox, выше средней у Nanoflare). Замеры в карточке не меняются, сдвигается только характер.
{
  const YX_POW=/^Astrox (100|99|88D)/,ARC=/^Arcsaber/,D_=.2;
  const grp=(o,re)=>R.filter(x=>x.b==='Yonex'&&x.lv===o.lv&&x.u===o.u&&re.test(x.m));
  const fit=o=>{
    const P=grp(o,YX_POW),N=grp(o,/^Nanoflare/);if(!P.length&&!N.length)return;
    o.ser0=o.ser0||{...o.ax};
    let lo=P.length?Math.max(...P.map(x=>x.ax.speed))+D_:1,hi=N.length?Math.min(...N.map(x=>x.ax.speed))-D_:10;
    if(lo>hi)lo=hi=(lo+hi)/2;
    o.ax.speed=Math.max(lo,Math.min(hi,o.ax.speed));
    // мощь — между средней у силовых Astrox и средней у Nanoflare того же класса и веса (только снижаем)
    const avg=a=>a.reduce((x,y)=>x+y,0)/a.length;
    if(P.length){let cap=avg(P.map(x=>x.ax.power))-D_;const fl=N.length?avg(N.map(x=>x.ax.power))+D_:1;if(cap<fl)cap=(cap+fl)/2;o.ax.power=Math.max(1,Math.min(o.ax.power,cap))}
    o.yxFit={lo,hi};
  };
  const arc=R.filter(o=>o.b==='Yonex'&&ARC.test(o.m));
  arc.filter(o=>/^Arcsaber 11/.test(o.m)).forEach(fit);
  arc.filter(o=>!/^Arcsaber 11/.test(o.m)).forEach(fit);
  // Arcsaber 7 остаётся на своём расстоянии от 11 того же уровня (как в SERIES), в пределах коридора между Astrox и Nanoflare
  (SERIES['Yonex|Arcsaber'].gap||[]).forEach(([m,k,over,d])=>arc.filter(o=>o.m===m).forEach(o=>{
    const ref=arc.find(x=>x.m===over&&x.u===o.u);const b=LV_BAND[o.lv];if(!ref||!b)return;
    let v=ref.ax[k]+d*(b[1]-b[0])/9;
    if(k==='speed'&&o.yxFit)v=Math.max(o.yxFit.lo,Math.min(o.yxFit.hi,v));
    o.ax[k]=Math.max(1,Math.min(10,v))}));
}
// ---- переходы профессиональных игроков: модели, между которыми игроки переходили, близки по ощущениям
const LINEAGE=[
 ['Aeronaut 9000C','Halbertec 9000','Юта Ватанабэ перешёл с Aeronaut 9000C на Halbertec 9000','Yuta Watanabe switched from the Aeronaut 9000C to the Halbertec 9000'],
 ['Tectonic 9','AXForce 80','Джонатан Кристи играл Tectonic 9, затем перешёл на AXForce 80','Jonatan Christie played the Tectonic 9, then switched to the AXForce 80'],
 // ближайшие аналоги между брендами — оценка Uniqsport
 ['Calibar 900B','Halbertec 9000','Ближайший аналог по ощущениям профессионального игрока — оценка Uniqsport','Closest match in feel for a professional player, according to Uniqsport'],
 ['AXForce 100','Astrox 100ZZ','Ближайший аналог в другом бренде по оценке Uniqsport','Closest match from another brand, according to Uniqsport'],
 ['AXForce 70','Astrox 77 Pro','Ближайший аналог в другом бренде по оценке Uniqsport','Closest match from another brand, according to Uniqsport'],
 ['AXForce 80','Astrox 88D','Ближайший аналог в другом бренде по оценке Uniqsport','Closest match from another brand, according to Uniqsport'],
 ['AXForce 90 MAX Tiger','Astrox 88D','Ближайший аналог в другом бренде по оценке Uniqsport','Closest match from another brand, according to Uniqsport'],
 ['BladeX 800','Nanoflare 800 Pro','Ближайший аналог в другом бренде по оценке Uniqsport','Closest match from another brand, according to Uniqsport'],
];
const lineageOf=(a,b)=>LINEAGE.find(([x,y])=>(a.m===x&&b.m===y)||(a.m===y&&b.m===x));

// Yandex.Metrica goals (counter 37968945); on the live site ym() is defined by the counter code
const YM=37968945;const goal=(n,p)=>{try{if(typeof window.ym==='function')window.ym(YM,'reachGoal',n,{...(p||{}),lang:LANG})}catch(e){}};let started=false;
// исключены из подбора и поиска: самые стартовые ракетки
const NO_RECOMMEND=new Set(['AXForce 9']);
const SITE=R.filter(o=>o.site&&!NO_RECOMMEND.has(o.m));
// ---- technology helpers
const ACR=/^(HDF|UHB|FRTP|TB|VDM|CSR|NAMD|ACC|RIF|HMG|NMG|PE|HCT|RTC|SRS|STD|EX|II|2G|T|M40X|M46|M50|T1100|E|B|DR|TR|ZZ|MM|PLUS)$/;
const cap=w=>ACR.test(w.replace(/[^A-Z0-9]/g,''))||/\d/.test(w)?w:w.charAt(0)+w.slice(1).toLowerCase();
const tname=n=>n.split(/\s+/).map(w=>w.split('-').map(cap).join('-')).join(' ');
const BASE=/^(ISOMETRIC|10 MM|DYNAMIC OPTIMUM FRAME|TB NANO|HOT MELT|SONIC BOOM)/;
function leadTech(o,ax,n){const j=AXK.indexOf(ax);return (o.t||[]).filter(i=>!BASE.test(TECH[i].n)).map(i=>[i,TECH[i].e[j]]).filter(x=>x[1]>0).sort((a,b)=>b[1]-a[1]).slice(0,n||2).map(x=>tname(TECH[x[0]].n))}
const ESS={power:['мощь в ударе','power on impact'],speed:['скорость маха','swing speed'],control:['стабильность удара','stability on impact'],ease:['лёгкость в освоении','ease of play'],comfort:['комфорт для руки','arm comfort']};
function essence(o){
  if(!o.hasT)return '';
  const top=AXK.map((k,j)=>[k,o.D[j]]).filter(x=>x[1]>=.62).sort((a,b)=>b[1]-a[1]).slice(0,2).map(x=>T(...ESS[x[0]]));
  return top.length?`<p class="essence"><b>${T('Суть:','In short:')}</b> ${T('технологии работают на','the technologies are built for')} ${top.join(T(' и ',' and '))}.</p>`:'';
}
// ---- questions
const Q={
 lvl:[['beg','Только начинаю','Just starting'],['am','Любитель, 1–3 года, играю для себя','Recreational, 1–3 years, for fun'],['adv','Больше 3 лет, тренируюсь и играю турниры','3+ years, train and play tournaments'],['pro','Профессионал','Professional']],
 who:[['m','Мужчина','Man'],['w','Женщина','Woman'],['teen','Подросток, 12–16 лет','Teenager, 12–16'],['kid','Ребёнок, до 12 лет','Child, under 12']],
 tier:[['opt','Оптимальную под мою игру','Best for my game'],['1','Начальную','Entry-level'],['2','Любительскую','Recreational'],['3','Полупрофессиональную','Semi-pro'],['4','Профессиональную','Professional']],
 fmt:[['single','Одиночка','Singles'],['back','Пара, сзади','Doubles, back court'],['net','Пара, у сетки','Doubles, at the net'],['mixd','Микст','Mixed doubles'],['mix','Всё подряд','A bit of everything']],
 style:[['atk','Атакую смэшем','Attack with smashes'],['def','Защита и контратака','Defence and counter-attack'],['all','Универсально','All-round']],
 swing:[['soft','Мягкий','Gentle'],['mid','Средний','Medium'],['fast','Резкий','Fast']],
 arm:[['no','Нет','No'],['some','Иногда','Sometimes'],['yes','Да, берегу руку','Yes, I need to protect it']],
 brand:[['any','Любой','Any'],['Li-Ning','Li-Ning'],['Yonex','Yonex']],
 man_u:[['3','3U'],['4','4U'],['5','5U'],['6','6U']],
 man_bt:[['K','В голову','Head-heavy'],['N','Нейтральный','Even'],['H','В ручку','Head-light'],['?','Не знаю','Not sure']],
 man_f:[['1','Гибкая','Flexible'],['3','Средняя','Medium'],['5','Жёсткая','Stiff'],['6','Очень жёсткая','Extra stiff'],['0','Не знаю','Not sure']],
 man_s:[['atk','Атакующая','Attacking'],['spd','Скоростная','Speed'],['uni','Универсальная','All-round'],['?','Не знаю','Not sure']],
 ltier:[['opt','Оптимальную','Best match'],['cheap','Дешевле','Cheaper'],['1','Начальную','Entry-level'],['2','Любительскую','Recreational'],['3','Полупрофессиональную','Semi-pro'],['4','Профессиональную','Professional']],
 dir:[['same','Такую же','The same'],['power','Мощнее','More power'],['speed','Быстрее','Faster'],['ease','Мягче, легче','Softer, lighter'],['comfort','Бережнее к руке','Easier on the arm']],
 brand2:[['any','Любой','Any'],['Li-Ning','Li-Ning'],['Yonex','Yonex']]
};
const DEF={ltier:'opt',man_u:'4',man_bt:'?',man_f:'0',man_s:'?',who:'m',tier:'opt',lvl:'am',fmt:'back',style:'atk',swing:'mid',arm:'some',brand:'any',dir:'speed',brand2:'any'};
document.querySelectorAll('.chips').forEach(box=>{
  const q=box.dataset.q;
  box.innerHTML=Q[q].map(([v,l,e])=>`<label class="chip"><input type="radio" name="${q}" id="${q}-${v}" value="${v}"${v===DEF[q]?' checked':''}><span>${T(l,e||l)}</span></label>`).join('');
});
const val=n=>(document.querySelector(`input[name="${n}"]:checked`)||{}).value;
const fmtP=p=>p?p.toLocaleString(LANG==='en'?'en-US':'ru-RU')+' ₽':T('цена на сайте','price on the website');
const label=o=>o.custom?o.m:`${o.b} ${o.m}`;
const STORE_BASE=RF.base||'https://uniqsport.ru/';const url=o=>STORE_BASE+'index.php?route=product/product&product_id='+o.id;
// ---- search
const LAT={а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'e',ж:'zh',з:'z',и:'i',й:'i',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'h',ц:'c',ч:'ch',ш:'sh',щ:'sh',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya'};
const ALIAS=[['драйвикс','drivex'],['драйв икс','drivex'],['трастер','thruster'],['траст','thruster'],['аураспид','aura speed'],['ауроспид','aura speed'],['auraspeed','aura speed'],['виктор','victor'],['акс форс','axforce'],['аксфорс','axforce'],['астрокс','astrox'],['нанофлэр','nanoflare'],['нанофлер','nanoflare'],['нанофлейр','nanoflare'],['блейд икс','bladex'],['блейдикс','bladex'],['блэйдикс','bladex'],['блейдекс','bladex'],['блейд','bladex'],['халбертек','halbertec'],['хальбертек','halbertec'],['хелбертек','halbertec'],['арксейбер','arcsaber'],['арксабер','arcsaber'],['арксэйбер','arcsaber'],['виндшторм','windstorm'],['виндсторм','windstorm'],['аэронавт','aeronaut'],['аэронаут','aeronaut'],['калибар','calibar'],['тектоник','tectonic'],['дуора','duora'],['ли нинг','lining'],['линин','lining'],['лининг','lining'],['йонекс','yonex'],['ёнекс','yonex'],['кэннон','cannon'],['кэнон','cannon'],['канон','cannon'],['бигбэнг','bigbang'],['биг бэнг','bigbang'],['бигбенг','bigbang'],['макс','max'],['тур','tour'],['гейм','game'],['плей','play'],['спид','speed'],['пауэр','power'],['пауер','power'],['лайт','light'],['спирал','spiral'],['сонар','sonar'],['некстейдж','nextage'],['виктор','victor'],['трастер','thruster'],['тhrastер','thruster'],['аура спид','auraspeed'],['ауроспид','auraspeed'],['максх','maxx'],['макс х','maxx'],['фелет','felet'],['лин мей','lingmei'],['линмей','lingmei'],['ген','gen'],['экс','ex'],['стронг','strong']];
ALIAS.sort((x,y)=>y[0].length-x[0].length);
const norm=s=>{s=' '+s.toLowerCase().replace(/ё/g,'е')+' ';for(const[a,b]of ALIAS)s=s.split(a).join(b);s=s.replace(/[а-я]/g,c=>LAT[c]??c);return s.replace(/li-?ning/g,'lining').replace(/[^a-z0-9]+/g,' ').trim()};
R.forEach(o=>{o.key=norm(`${o.b} ${o.m} ${o.mname||''} ${o.u}u`).replace(/ /g,'')});
const qIn=document.getElementById('ref-q'),qList=document.getElementById('ref-list'),picked=document.getElementById('ref-picked');
let refMode='catalog',refId=null,sugg=[],act=-1;
function search(q){
  const toks=norm(q).split(' ').filter(Boolean);if(!toks.length)return [];
  return R.filter(o=>!NO_RECOMMEND.has(o.m)&&toks.every(tk=>o.key.includes(tk))).sort((a,b)=>(b.site-a.site)||a.key.length-b.key.length||a.u-b.u).slice(0,8);
}
function showSugg(){
  const q=qIn.value.trim();sugg=search(q);act=sugg.length?0:-1;
  if(!q){qList.hidden=true;qIn.setAttribute('aria-expanded','false');return}
  qList.innerHTML=(sugg.length?sugg.map((o,i)=>`<li role="option" data-id="${o.uid}" aria-selected="${i===act}">${label(o)} ${o.u}U<span class="s">${o.bp} ${T('мм','mm')} · ${o.swEst?T('по каталогу','catalogue data'):T('измерено в Uniqsport','measured by Uniqsport')}${o.site?'':' · <span class="ref">'+T('нет в магазине','not sold in store')+'</span>'}${o.old?T(' · модель прошлых лет',' · older model'):''}</span></li>`).join(''):`<li class="none">${T('«'+q.replace(/[<>&]/g,'')+'» не нашли','No match for “'+q.replace(/[<>&]/g,'')+'”')}</li>`)+'<li class="add" data-manual="1">'+T('Нет в списке — ввести характеристики','Not listed? Enter the specs')+'</li>';
  qList.hidden=false;qIn.setAttribute('aria-expanded','true');
  clearTimeout(window._nf);if(!sugg.length&&q.length>=4)window._nf=setTimeout(()=>goal('podbor_notfound',{q}),1500);
}
function setRef(uid){const o=R.find(x=>x.uid===String(uid));if(!o)return;refMode='catalog';refId=o.uid;if(ownMode==='like')own=null;document.getElementById('ltier-opt').checked=true;qIn.value=`${label(o)} ${o.u}U`;qList.hidden=true;qIn.setAttribute('aria-expanded','false');
  pickedTxt=()=>`${o.swEst?T('Измерения по каталогу','Catalogue specs'):T('Измерено в Uniqsport','Measured by Uniqsport')} · ${T('баланс','balance')} ${o.bp} ${T('мм','mm')} · ${T('маховый вес','swing weight')} ${num1(o.SW)} ${T('кг·см²','kg·cm²')}${o.swEst?T(' (оценка)',' (estimate)'):''}${o.site?' · '+fmtP(o.p):T(' · нет в магазине',' · not sold in store')}`;picked.textContent=pickedTxt();showManual(false);render()}
let pickedTxt=()=>'';
function showManual(on){document.getElementById('f-manual').hidden=!on;document.getElementById('f-search').hidden=on;if(on){goal('podbor_manual',{q:qIn.value.trim()});refMode='manual';document.getElementById('ltier-opt').checked=true;if(ownMode==='like')own=null;const v=qIn.value.trim();const nm=document.getElementById('man-name');if(v&&!nm.value&&!search(v).length)nm.value=v;render()}}
qIn.addEventListener('input',showSugg);
qIn.addEventListener('focus',()=>{if(qIn.value)qIn.select()});
qIn.addEventListener('keydown',e=>{
  if(qList.hidden)return;
  if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(!sugg.length)return;act=(act+(e.key==='ArrowDown'?1:-1)+sugg.length)%sugg.length;qList.querySelectorAll('[role=option]').forEach((li,i)=>li.setAttribute('aria-selected',i===act))}
  if(e.key==='Enter'){e.preventDefault();if(act>=0)setRef(sugg[act].uid);else showManual(true)}
  if(e.key==='Escape'){qList.hidden=true}
});
qList.addEventListener('mousedown',e=>{e.preventDefault();const li=e.target.closest('li');if(!li)return;if(li.dataset.manual)showManual(true);else if(li.dataset.id)setRef(li.dataset.id)});
qIn.addEventListener('blur',()=>setTimeout(()=>{qList.hidden=true;qIn.setAttribute('aria-expanded','false')},120));
document.getElementById('to-manual').onclick=()=>showManual(true);
document.getElementById('to-search').onclick=()=>{showManual(false);refMode='catalog';render();qIn.focus()};
['man-name','man-bp','man-p','man-sw'].forEach(id=>document.getElementById(id).addEventListener('input',()=>render()));
function manualRef(){
  const u=+val('man_u'),bt=val('man_bt'),fv=+val('man_f'),sv=val('man_s');
  const bpIn=+document.getElementById('man-bp').value;const bpOk=bpIn>=270&&bpIn<=340;const bp=bpOk?bpIn:({K:305,N:295,H:288}[bt]||298);
  const swIn=+document.getElementById('man-sw').value;const swOk=swIn>=70&&swIn<=100;
  const W=UW[u]||82;
  const o={uid:'custom',b:'',m:document.getElementById('man-name').value.trim()||T('Ваша ракетка','Your racket'),s:{atk:'AXForce',spd:'BladeX',uni:'Halbertec'}[sv]||'',u,bp,f:fv||3,fe:0,d:null,lv:2,p:+document.getElementById('man-p').value||0,W,
    SW:swOk?swIn:(FIT.sw[0]+FIT.sw[1]*W+FIT.sw[2]*bp),H:FIT.h[0]+FIT.h[1]*W+FIT.h[2]*bp,swEst:!swOk,t:[],hasT:false,tsum:[0,0,0,0,0],custom:true,bpGuess:!bpOk,site:false};
  o.ax=stretch(rawAx(o));return o;
}
// ---- player profile
function targetProfile(){
  const lvl=val('lvl'),who0=val('who'),fmt=(who0==='teen'||who0==='kid')?'mix':val('fmt'),style=val('style'),swing=val('swing'),arm=val('arm');
  let t={power:{atk:7.5,def:4,all:6}[style],speed:{atk:5,def:7.5,all:6.2}[style],control:{atk:6,def:6,all:7}[style],ease:{beg:8.5,am:5.8,adv:4.3,pro:3.0}[lvl],comfort:{no:4.5,some:6.5,yes:8.5}[arm]};
  if(fmt==='back'){t.power+=1;t.speed-=.3}
  if(fmt==='net'){t.speed+=1.8;t.power-=1.5;t.control+=.5}
  if(fmt==='single'){t.control+=1.2;t.power+=.3}
  if(fmt==='mix'){t.control+=.5}
  // микст: мужчина сзади в одиночку закрывает корт — нужна атака плюс быстрые драйвы и защита (у профи: Astrox 77 Pro, Astrox 88S, Arcsaber 11 Pro, Halbertec 9000);
  // женщина у сетки — скорость рук и точность (Astrox 77, Nanoflare 700)
  if(fmt==='mixd'&&who0!=='w'){t.speed+=.3;t.control+=1.5}
  if(fmt==='mixd'&&who0==='w'){t.speed+=1.4;t.control+=.8;t.power-=1.0}
  if(lvl==='adv'||lvl==='pro'){t.control+=.8}
  if(lvl==='beg')t.comfort+=.5;
  if(swing==='soft')t.ease+=1.5; if(swing==='fast')t.ease-=1.5;
  if(arm==='some')t.ease+=.2; if(arm==='yes')t.ease+=.8;
  const who=val('who'),kL=KL[lvl];
  if(who==='w'){t.ease+=1.8*kL;t.power-=1.0*kL;t.speed+=.8*kL;t.comfort+=1.2*kL}
  if(who==='teen'){t.ease+=2.0*kL+.5;t.power-=1.0*kL;t.speed+=.5;t.comfort+=1.0}
  if(who==='kid'){t.ease+=3.0;t.power-=2.0;t.control-=.5;t.speed+=.5;t.comfort+=2.0}
  for(const k in t)t[k]=Math.max(1,Math.min(9.5,t[k]));
  // мощь, скорость и контроль игрока — в коридоре его уровня (как у ракеток): новичок 1–5, любитель 2,5–6,5, продвинутый 4–8,5, профи 5,5–10
  // если класс выбран явно — профиль ставим в коридор этого класса, чтобы сравнивать ракетки внутри него
  const PB=/^[1-4]$/.test(val('tier'))?LV_BAND[+val('tier')]:{beg:[1,5],am:[2.5,6.5],adv:[4,8.5],pro:[5.5,10]}[lvl];
  if(PB)['power','speed','control'].forEach(k=>{t[k]=band(t[k],PB)});
  return t;
}
const WGT={power:1,speed:1,control:.7,ease:1.2,comfort:.8};
const KL={beg:1,am:.9,adv:.6,pro:.35};
const ATK_TILT=new Set(['Halbertec 9000 Power']);
const STYLE_BONUS=3;
const LN_BONUS=3; // приоритет Li-Ning в выдаче, в баллах совпадения
function dist(a,t){let d=0,w=0;for(const k in WGT){d+=WGT[k]*Math.abs(a[k]-t[k]);w+=WGT[k]}return d/w}
function scoreQuiz(o,t){
  const lvl=val('lvl'),swing=val('swing'),arm=val('arm'),cls=val('tier'),top=cls!=='opt';
  let s=100-dist(o.ax,t)*14;const tips=[];
  if(swing==='soft'&&o.f>=5){s-=12;tips.push(T('на старте натяните на 10–11 кг — жёсткий стержень раскроется легче','string it at 10–11 kg to start — a stiff shaft is easier to load that way'))}
  if(swing==='fast'&&o.f&&o.f<=1&&(lvl==='adv'||lvl==='pro')){s-=6;tips.push(T('натяните плотнее, 12–13 кг — гибкий стержень будет точнее при резком замахе','string it tighter, 12–13 kg — a flexible shaft stays more accurate with a fast swing'))}
  if(arm==='yes'&&o.u<=3){s-=10;const lighter=SITE.find(x=>x.b===o.b&&x.m===o.m&&x.u>o.u);tips.push(lighter?T(`эта модель есть в ${lighter.u}U — руке будет легче`,`this model also comes in ${lighter.u}U — easier on your arm`):T('3U тяжелее для руки, не забывайте разминать кисть и локоть','3U is harder on the arm — warm up your wrist and elbow'))}
  if(arm==='some'&&o.u<=3){s-=4}
  if(lvl==='beg'&&o.u<=3){s-=top?3:6}
  if(!top){
    if(lvl==='beg'&&o.lv>=4){s-=9}
    if(lvl==='beg'&&o.lv===3){s-=4}
    if(lvl==='am'&&o.lv===4&&o.f>=5){s-=4}
    if((lvl==='pro'||lvl==='adv')&&o.lv===1){s-=8}
    if(lvl==='pro'&&o.lv===2){s-=6}
  }
  const who=val('who'),kL=KL[lvl];
  if(who==='w'){if(o.u<=3)s-=10*kL;if(o.u>=5)s+=3*kL;if(o.f>=5)s-=4*kL}
  if(who==='teen'){if(o.u<=3)s-=10;if(o.u>=5)s+=3;if(o.f>=5)s-=8*kL}
  if(who==='kid'){if(o.u<=4)s-=14;if(o.u>=5)s+=6;if(o.f>=4)s-=10;if(o.lv===1)s+=3;if(o.lv>=3&&!top)s-=6}
  if(ATK_TILT.has(o.m)&&val('style')!=='atk')s-=5;
  // классификация Li-Ning (атака / скорость / контроль): серия по направлению стиля игры получает небольшой плюс
  if(o.sAx&&o.sAx==={atk:'power',def:'speed',all:'control'}[val('style')])s+=STYLE_BONUS; // атакующая версия модели — универсалам и защитникам ниже базовой
  if(o.b==='Li-Ning')s+=LN_BONUS;
  if((lvl==='beg'||lvl==='am')&&o.lv===4)tips.push(T('это флагман на вырост — ракетка будет расти вместе с вашей техникой','a flagship to grow into — the racket will keep up as your technique improves'));
  if(who==='kid'||who==='teen'||(who==='w'&&(lvl==='beg'||lvl==='am')))tips.push(T('если ладонь небольшая, возьмите ручку G6 — она тоньше и удобнее','for a smaller hand, choose a G6 grip — it is thinner and more comfortable'));
  return {s:Math.max(0,s),tips};
}
function reasons(o,t){
  const r=[];const swing=val('swing'),fmt=val('fmt');
  const fits=k=>t[k]>=6&&o.ax[k]>=t[k]-1.5;
  const tl=(k,pre)=>{const x=leadTech(o,k);return x.length?(pre||'')+x.join(T(' и ',' and ')):''};
  const phrase={
    power:()=>T(`Мощный удар: ${o.swEst?'баланс '+o.bp+' мм':'маховый вес '+num1(o.SW)+' кг·см²'}${tl('power',', ')}`,`Powerful hitting: ${o.swEst?'balance '+o.bp+' mm':'swing weight '+num1(o.SW)+' kg·cm²'}${tl('power',', ')}`),
    speed:()=>T(`Быстрая в руке: ${tl('speed')||(o.bp<=296?'вес смещён к ручке':'компактный маховый вес')}${o.swEst?'':', маховый вес '+num1(o.SW)}`,`Quick in the hand: ${tl('speed')||(o.bp<=296?'weight sits towards the handle':'compact swing weight')}${o.swEst?'':', swing weight '+num1(o.SW)}`),
    control:()=>T(`Стабильная на ударе${tl('control',': ')||' — легче держать направление'}`,`Stable on impact${tl('control',': ')||' — easier to keep the direction'}`),
    ease:()=>T(`Легко разгонять волан: ${o.f?FX(o.f)+' стержень, ':''}вес ${o.u}U${tl('ease',', ')}`,`Easy to generate shuttle speed: ${o.f?FX(o.f)+' shaft, ':''}${o.u}U weight${tl('ease',', ')}`),
    comfort:()=>T(`Бережёт руку: ${tl('comfort')||flexTxt(o)}`,`Easy on the arm: ${tl('comfort')||flexTxt(o)}`)
  };
  const order=AXK.slice().sort((a,b)=>t[b]-t[a]);
  for(const k of order){
    if(!fits(k))continue;
    if(k==='ease'&&!((o.f||3)<=3&&o.u>=4))continue;
    r.push(phrase[k]());
  }
  if(!r.length){
    const k=(t.ease>=7&&(o.f||3)<=3)?'ease':(order.filter(k=>t[k]>=5&&!(k==='ease'&&(o.f||3)>3)).sort((a,b)=>(o.ax[b]-t[b])-(o.ax[a]-t[a]))[0]||order[0]);
    r.push(phrase[k]());
  }
  const who=val('who');
  if(who==='kid'&&o.u>=5)r.unshift(T(`Лёгкая полноразмерная ракетка ${o.u}U — ребёнок сразу привыкает к правильной длине`,`A light full-size ${o.u}U racket — the child gets used to the right length from the start`));
  if((who==='w'||who==='teen')&&o.u>=5)r.push(T(`Вес ${o.u}U — легко управлять и долго играть без усталости`,`${o.u}U weight — easy to handle and comfortable for long sessions`));
  if(swing==='fast'&&o.f>=4)r.push(T(`${cap1(FX(o.f))} стержень отдаст энергию резкому замаху`,`The ${FX(o.f)} shaft returns the energy of a fast swing`));
  if(fmt==='net'&&o.bp<=295)r.push(T('Хороша у сетки: быстро возвращается в стойку','Good at the net: quick to get back to the ready position'));
  return r.slice(0,3);
}
function filterBase(o,brandKey,stockId){
  if(!o.site)return false;
  const br=val(brandKey);if(br&&br!=='any'&&o.b!==br)return false;
  if(document.getElementById(stockId).checked&&o.q!==1)return false;
  return true;
}
// ---- приоритет в выдаче среди подходящих моделей (в пределах TIER_WIN баллов от лучшей)
// основной товар — Li-Ning действующих серий; прошлые серии больше не поставляются
const CURRENT={'Li-Ning':['AXForce','BladeX','Halbertec','WindStorm'],'Yonex':['Astrox','Nanoflare','Arcsaber']};
const isNew=o=>(CURRENT[o.b]||[]).includes(o.s);
const TIER_WIN=12;
// ---- основная выдача — только по совпадению: верный вес каждой модели; наличие на порядок не влияет.
// новые серии получают небольшую поблажку в сортировке (не в проценте), чтобы при равном совпадении шли выше
const NEW_BONUS=5,STOCK_WIN=8;
function fitSort(list){
  if(!list.length)return list;
  const bestOf=new Map();list.forEach(x=>{const k=x.o.b+x.o.m;if(!bestOf.has(k)||bestOf.get(k).s<x.s)bestOf.set(k,x)});
  list.forEach(x=>{const b=bestOf.get(x.o.b+x.o.m);x.right=b===x;x.rightU=b.o.u});
  return list.filter(x=>x.right).sort((a,b)=>(b.s+(isNew(b.o)?NEW_BONUS:0))-(a.s+(isNew(a.o)?NEW_BONUS:0)));
}
// ---- отдельный блок «Есть в наличии»: ближайшие к лучшему совпадению ракетки со склада того же направления.
// порядок: новые Li-Ning верного веса → новые Li-Ning другого веса → новые Yonex → остальные
function stockPicks(all,main,n=3){
  if(!main.length)return [];
  const lead=main[0],best=lead.s,shown=new Set(main.slice(0,3).map(x=>x.o.uid));
  if(main.slice(0,3).every(x=>x.o.q===1))return [];
  const sameDir=o=>!lead.o.sAx||!o.sAx||o.sAx===lead.o.sAx;
  let pool=all.filter(x=>x.o.q===1&&x.s>=best-STOCK_WIN&&!shown.has(x.o.uid)&&sameDir(x.o));
  const seen=new Map();pool.forEach(x=>{const k=x.o.b+x.o.m;if(!seen.has(k)||seen.get(k).s<x.s)seen.set(k,x)});
  pool=[...seen.values()];
  const tier=x=>{const o=x.o,ln=o.b==='Li-Ning'&&isNew(o),yx=o.b==='Yonex'&&isNew(o);if(ln&&x.right)return 0;if(ln)return 1;if(yx)return 2;return 3};
  pool.forEach(x=>{if(!x.right&&x.rightU!==x.o.u)x.stockNote=T(`по ощущениям лучше подходит ${x.rightU}U — его нет в наличии, а ${x.o.u}U есть`,`${x.rightU}U suits you better, but it is out of stock — ${x.o.u}U is in stock`)});
  return pool.sort((a,b)=>tier(a)-tier(b)||b.s-a.s).slice(0,n);
}
const stockHTML=(picks,t,extra)=>picks.length?`<div class="stockpick"><h4>${T('Есть в наличии — можно забрать сразу','In stock — available right away')}</h4><p>${T('Подбор по наличию: ближайшие к вашему профилю ракетки того же направления, которые есть на складе. Основная рекомендация — выше.','Picked from stock: the rackets of the same type closest to your profile that are in the warehouse now. The main recommendation is above.')}</p><div class="cards">${picks.map((x,i)=>cardHTML(x,t,3+i,extra)).join('')}</div></div>`:'';
// одна строка на модель и вес
const firstPerModel=list=>{const seen=new Set();return list.filter(x=>{const k=x.o.b+x.o.m+x.o.u;if(seen.has(k))return false;seen.add(k);return true})};
// модели, которые мужчинам не предлагаем: дизайн и позиционирование женские
const WOMEN_ONLY=new Set(['AXForce 80 Light']);
// из весов одной модели берём лучший; вариант в наличии предпочитаем, если он хуже не больше чем на 5 баллов
function bestPerModel(list){const seen=new Map(),v=x=>x.s+(x.o.q===1?5:0);for(const x of list){const k=x.o.b+x.o.m;if(!seen.has(k)||v(seen.get(k))<v(x))seen.set(k,x)}return [...seen.values()].sort((a,b)=>b.s-a.s)}
function axesHTML(o,t){
  return `<div class="axes">${AXN().map(([k,l])=>`<div class="axis"><span>${l}</span><div class="bar"><div class="fill" style="width:${o.ax[k]*10}%"></div>${t?`<div class="tgt" style="left:${t[k]*10}%" title="${T('Цель','Target')}: ${num1(t[k])}"></div>`:''}</div><span class="v">${o.ax[k].toFixed(0)}</span></div>`).join('')}</div>`;
}
function techHTML(o){
  if(!o.hasT)return '';
  const list=(o.t||[]).map(i=>TECH[i]).filter(x=>x.e.some(v=>v!==0));
  if(!list.length)return '';
  return `<details class="tech"><summary>${T('Технологии','Technologies')} (${list.length}) — ${T('что они дают','what they do')}</summary><ul>${list.map(x=>`<li><b>${tname(x.n)}</b><span class="ef">${T(x.h,x.he||x.h)}</span></li>`).join('')}</ul></details>`;
}
function cardHTML(x,t,i,extra){
  const o=x.o;
  return `<article class="card${i===0?' best':''}">
    <div style="min-width:0">
      <h3>${label(o)} <span style="font-weight:500;color:var(--muted)">${o.u}U</span></h3>
      <div class="meta"><span>${T('Баланс','Balance')} <b>${o.bp} ${T('мм','mm')}</b>${/^официальный/.test(o.bpsrc||'')?T(' (офиц.)',' (official)'):/^(оценка|как у)/.test(o.bpsrc||'')?T(' (оценка)',' (estimate)'):''}</span><span>${T('Маховый вес','Swing weight')} <b>${num1(o.SW)}</b>${o.swEst?T(' (оценка)',' (estimate)'):''}</span><span>${T('Стержень','Shaft')} <b>${o.f?FX(o.f):T('нет данных','no data')}</b></span>${o.d?`<span>Ø <b>${num1(o.d)} ${T('мм','mm')}</b></span>`:''}<span><b>${fmtP(o.p)}</b></span>
      ${o.q===1?'<span class="badge stock">'+T('В наличии','In stock')+'</span>':o.q===0?'<span class="badge out">'+T('Нет в наличии','Out of stock')+'</span>':''}${o.st?'<span class="badge strung">'+T('Натянута','Strung')+'</span>':''}${o.lv?`<span class="badge">${cap1(LVN(o.lv))}</span>`:''}${o.swEst?`<span class="badge est" title="${T('Вес, баланс и жёсткость — из каталогов производителя; маховый вес рассчитан','Weight, balance and stiffness from the manufacturer’s catalogue; swing weight calculated')}">${T('Измерения по каталогу','Catalogue specs')}</span>`:`<span class="badge meas" title="${T('Без струн, с родным грипом','Unstrung, with the original grip')}">${T('Измерено в Uniqsport','Measured by Uniqsport')}${o.n>1?' · '+o.n+T(' шт.',' pcs'):''}</span>`}${o.cor?`<span class="badge est" title="${T('Видно только в прототипе','Visible in the prototype only')}">${T('С поправкой магазина','Store adjustment')}</span>`:''}</div>
      ${essence(o)}
      <ul class="why">${x.why.map(w=>`<li>${w}</li>`).join('')}${(x.tips||[]).slice(0,2).map(w=>`<li class="tip"><b>${T('Совет:','Tip:')}</b> ${w}</li>`).join('')}</ul>
      ${techHTML(o)}
      <div class="actions"><a class="btn" href="${url(o)}" target="_blank" rel="noopener" data-goal="podbor_card" data-id="${o.id}">${T('Открыть карточку','View in store')}</a><button type="button" class="btn ghost" data-like="${o.uid}">${T('Похожие по характеру','Similar rackets')}</button></div>
    </div>
    <div><div class="score"><span class="n">${x.s}%</span><span class="l">${extra||T('совпадение с вашим профилем','match with your profile')}</span></div>${axesHTML(o,t)}</div>
  </article>`;
}
function rowHTML(x){const o=x.o;return `<div class="row"><span class="pct">${x.s}%</span><div style="min-width:0"><a href="${url(o)}" target="_blank" rel="noopener">${label(o)} ${o.u}U</a><div class="sub">${o.bp} ${T('мм','mm')} · ${T('маховый','swing weight')} ${num1(o.SW)}${o.swEst?'*':''} · ${o.q===1?T('в наличии','in stock'):o.q===0?T('нет в наличии','out of stock'):T('наличие на сайте','check stock online')}</div></div><span class="pr">${fmtP(o.p)}</span></div>`}
const out=document.getElementById('results');
const slBox=document.getElementById('sliders');let own=null,ownMode=null;
slBox.innerHTML=AXN().map(([k,l])=>`<label class="sl" for="sl-${k}"><span>${l}</span><input type="range" id="sl-${k}" min="1" max="10" step="0.5"><output id="so-${k}"></output></label>`).join('');
function effT(base){return own&&ownMode===mode?{...own}:base}
function syncTune(t,title,hint){
  AXK.forEach(k=>{const i=document.getElementById('sl-'+k);if(document.activeElement!==i)i.value=t[k];document.getElementById('so-'+k).textContent=num1(+i.value)});
  const isOwn=!!(own&&ownMode===mode);
  document.getElementById('tune-h').textContent=title;
  const st=document.getElementById('tune-state');st.textContent=isOwn?T('свои значения','your values'):(mode==='quiz'?T('по ответам','from your answers'):T('по ракетке','from the racket'));st.classList.toggle('own',isOwn);
  document.getElementById('tune-reset').hidden=!isOwn;document.getElementById('tune-reset').textContent=mode==='quiz'?T('Вернуть по ответам','Reset to answers'):T('Вернуть по ракетке','Reset to racket');
  document.getElementById('tune-hint').textContent=hint;
}
slBox.addEventListener('input',()=>{const fresh=!(own&&ownMode===mode);own={};AXK.forEach(k=>own[k]=+document.getElementById('sl-'+k).value);ownMode=mode;if(fresh)goal('podbor_own_profile',{mode});render()});
document.getElementById('tune-reset').onclick=()=>{own=null;render()};
function renderQuiz(){
  {const w=val('who'),fs=document.querySelector('[data-q="fmt"]').closest('fieldset');fs.hidden=(w==='teen'||w==='kid')} // подростки и дети играют всё подряд — вопрос не задаём
  const t=effT(targetProfile());const cls=val('tier'),top=cls!=='opt';
  syncTune(t,T('Ваш профиль игрока','Your player profile'),own&&ownMode==='quiz'?T('Подбор идёт по вашим значениям. Ответы слева сейчас не влияют на профиль.','Matching uses your values. The answers on the left do not affect the profile right now.'):T('Профиль собран по ответам. Двигайте ползунки, если хотите задать свои значения — например, мощь 9, скорость 5.','The profile is built from your answers. Move the sliders to set your own values — for example, power 9, speed 5.'));
  const men=val('who')==='m',armCare=val('arm')==='yes',swingQ=val('swing'),elbow=val('arm')!=='no';
  // вес по замаху и локтю (решение магазина 5 ноября 2026):
  // 3U — только резкий (сильный) замах и локоть не болит; локоть болит («иногда» тоже) и резкий замах — 4U;
  // локоть болит и средний замах — 4U и AXForce 90 5U (даже мужчинам); просто средний замах — только 4U.
  // Мужчинам 5U и легче в остальных случаях не предлагаем (кроме мягкого замаха и «берегу руку»: 5U с балансом в голову).
  const ELBOW_5U=new Set(['AXForce 90 New']);
  const menOk=o=>{
    if(o.u<=3)return swingQ==='fast'&&!elbow;
    if(!men)return true;
    if(WOMEN_ONLY.has(o.m))return false;
    if(o.u===4)return true;
    if(swingQ==='mid')return elbow&&o.u===5&&ELBOW_5U.has(o.m);
    if(swingQ==='fast')return false;
    return armCare&&o.u===5&&o.bt==='K'};
  let list=SITE.filter(o=>filterBase(o,'brand','instock')&&(!top||o.lv===+cls)&&menOk(o)).map(o=>{const sc=scoreQuiz(o,t);return {o,s:Math.max(0,sc.s),tips:sc.tips}});
  // шкала совпадения: лучший — не выше 99, порядок по точным баллам сохраняется
  {const mx=Math.max(0,...list.map(x=>x.s));const sh=mx>99?mx-99:0;list.forEach(x=>{x.s=Math.max(0,Math.round(x.s-sh))})}
  const allQ=list;list=fitSort(list);
  const sp=document.getElementById('instock').checked?[]:stockPicks(allQ,list);
  sp.forEach(x=>{if(x.stockNote)x.tips=[x.stockNote,...(x.tips||[])]});
  [...list,...sp].forEach(x=>x.why=reasons(x.o,t));
  const spU=new Set(sp.map(x=>x.o.b+x.o.m)),tp=list.slice(0,3),more=list.slice(3).filter(x=>!spU.has(x.o.b+x.o.m)).slice(0,5);
  const clsP=SITE.filter(o=>top&&o.lv===+cls&&o.p).map(o=>o.p),minP=clsP.length?Math.min(...clsP):0,maxP=clsP.length?Math.max(...clsP):0;
  out.innerHTML=`${top?`<p class="topnote">${T(`Показываем только класс «${LVN(+cls)}»${minP?` (от ${fmtP(minP)} до ${fmtP(maxP)})`:''} — из него выбраны ракетки, которые лучше всего подходят под вашу игру. «Оптимальную» — подбор из всех классов с учётом вашего уровня.`,`Showing ${LVN(+cls)} rackets only${minP?` (${fmtP(minP)} to ${fmtP(maxP)})`:''} — picked from this class to suit your game best. “Best for my game” searches all classes with your level in mind.`)}</p>`:''}
  <div class="legend"><span><i class="lg-fill"></i>${T('характер ракетки','racket character')}</span><span><i class="lg-tgt"></i>${T('ваш профиль','your profile')}</span></div>
  ${tp.length?`<div class="cards">${tp.map((x,i)=>cardHTML(x,t,i)).join('')}</div>`:'<div class="panel empty">'+T('Под эти условия ракеток не нашлось. Снимите «Только в наличии» или выберите другой класс.','No rackets match these conditions. Untick “In stock only” or choose another class.')+'</div>'}
  ${stockHTML(sp,t)}
  ${more.length?`<div class="more"><h4>${top?T('Ещё из этого класса','More from this class'):T('Ещё подходят','Also a good fit')}</h4><div class="rowlist">${more.map(rowHTML).join('')}</div></div>`:''}
  <p class="note">${T('Маховый вес со звёздочкой — оценка по весу и балансу, остальные — замер на станке магазина. Где производитель указывает баланс в мм, используется он, а замеренный маховый вес пересчитан под него. Характер учитывает замеры, технологии рамы и стержня и ручные поправки магазина; при близком совпадении выше стоят ракетки Li-Ning.','Swing weights marked with an asterisk are estimated from weight and balance; the rest were measured on the store’s machine. Where the manufacturer states the balance in mm, that figure is used and the measured swing weight is adjusted to it. The character score combines measurements, frame and shaft technologies and the store’s own adjustments; on a close match, Li-Ning rackets rank higher.')}</p>`;
}
function renderLike(){
  const ref=refMode==='manual'?manualRef():R.find(o=>o.uid===refId);if(!ref){document.getElementById('tune').hidden=true;out.innerHTML='<div class="panel empty">'+T('Начните вводить название ракетки — например, «аксфорс 80» или «Astrox 88D».','Start typing a racket name — for example, “AXForce 80” or “Astrox 88D”.')+'</div>';return}
  const dir=val('dir');let t={...ref.ax};
  if(dir==='power'){t.power+=1.5;t.speed-=.5}
  if(dir==='speed'){t.speed+=1.5;t.power-=.5}
  if(dir==='ease'){t.ease+=1.8;t.control-=.3}
  if(dir==='comfort'){t.comfort+=2;t.ease+=.5}
  for(const k in t)t[k]=Math.max(1,Math.min(10,t[k]));
  // «Дешевле»: желаемый характер переносим в коридор класса ниже, чтобы шкалы ракеток и профиля совпадали
  {const tr=val('ltier');if(tr==='cheap'&&ref.lv>1&&LV_BAND[ref.lv]){const b0=LV_BAND[ref.lv],b1=LV_BAND[ref.lv-1];['power','speed','control'].forEach(k=>{const raw=1+(t[k]-b0[0])*9/(b0[1]-b0[0]);t[k]=Math.max(1,Math.min(10,band(Math.max(1,Math.min(10,raw)),b1)))})}}
  t=effT(t);const isOwn=!!(own&&ownMode==='like');
  syncTune(t,T('Желаемый характер','Target character'),isOwn?T('Подбор идёт по вашим значениям, а не по выбранной ракетке.','Matching uses your values, not the selected racket.'):T('Характер выбранной ракетки с учётом пожелания. Двигайте ползунки, чтобы задать свои значения.','The selected racket’s character, shifted the way you asked. Move the sliders to set your own values.'));
  const tier=val('ltier'),fixCls=/^[1-4]$/.test(tier)?+tier:0,cheap=tier==='cheap';
  // «по ощущениям»: маховый вес, вес головы, баланс, вес и жёсткость
  const phys=o=>Math.abs(o.SW-ref.SW)/1.0+Math.abs(o.H-ref.H)/0.8+Math.abs(o.bp-ref.bp)/5+Math.abs(o.W-ref.W)/3+((o.f&&ref.f)?Math.abs(o.f-ref.f)*.4:.4);
  // вес: «такую же» — только тот же вес; «мощнее» — тот же или на класс тяжелее (лучше — больше мощи в том же весе);
  // «быстрее», «мягче, легче», «бережнее к руке» — тот же или на класс легче, тяжелее — никогда
  const uOk=o=>{if(ref.custom||!ref.u)return true;if(dir==='same')return o.u===ref.u;if(dir==='power')return o.u===ref.u||o.u===ref.u-1;return o.u===ref.u||o.u===ref.u+1};
  // класс: «Топовую» — только топ; «Оптимальную» — тот же класс; «Дешевле» — похожая из класса ниже (для начального — тот же класс, но дешевле)
  const lvT=cheap?Math.max(1,(ref.lv||2)-1):ref.lv;
  const lvOk=o=>fixCls?o.lv===fixCls:(!ref.lv||!o.lv||o.lv===lvT);
  const priceOk=o=>!cheap||!ref.p||(o.p&&o.p<ref.p);
  const base0=SITE.filter(o=>o.uid!==ref.uid&&!(o.b===ref.b&&o.m===ref.m)&&filterBase(o,'brand2','instock2')&&uOk(o)&&priceOk(o));
  const base=base0.some(lvOk)?base0.filter(lvOk):base0;
  // сравниваем характер без коридора уровня — чтобы любительская могла быть «такой же, как топовая, но классом ниже»
  const ub=(o,k)=>{const b=LV_BAND[o.lv];const v=o.ax[k];return b?1+(v-b[0])*9/(b[1]-b[0]):v};
  const P=o=>isOwn?{p:o.ax.power,s:o.ax.speed,c:o.ax.control}:{p:ub(o,'power'),s:ub(o,'speed'),c:ub(o,'control')};
  const r=P(ref),tg=isOwn?{p:t.power,s:t.speed,c:t.control}:{...r};
  if(!isOwn){if(dir==='power'){tg.p+=1.5;tg.s-=.5}if(dir==='speed'){tg.s+=1.5;tg.p-=.5}}
  const soft=dir==='ease'||dir==='comfort';
  let list=base.map(o=>{
    const q=P(o),cD=Math.sqrt(((q.p-tg.p)**2+(q.s-tg.s)**2+(q.c-tg.c)**2)/3);
    let s=100-cD*(dir==='same'?15:12)-(soft?0:Math.abs(o.ax.ease-t.ease)*1.5)-(dir==='comfort'?0:Math.abs(o.ax.comfort-t.comfort))-(isOwn||soft?0:phys(o)*(dir==='same'?1.5:.6));
    if(!isOwn){
      // мощнее: прежде всего больше мощи в том же весе; более тяжёлый вес — только как запасной вариант
      if(dir==='power'){const g=q.p-r.p;s+=g>0?Math.min(g,3)*6:-25;if(o.u<ref.u)s-=8}
      if(dir==='speed'){const g=q.s-r.s;s+=g>0?Math.min(g,2.5)*4:-25;if(o.u>ref.u)s-=3}
      if(soft){
        // приоритет: стержень мягче → маховый вес меньше → вес меньше
        const df=(ref.f&&o.f)?ref.f-o.f:0,dsw=ref.SW-o.SW,dw=ref.W-o.W;
        if(df<0)s-=20;if(dsw<-.5)s-=15;
        // одна ступень мягче стержень ≈ на 3 кг·см² легче в замахе (примерно класс веса): стержень чуть важнее, но заметно более лёгкая ракетка тоже поднимается
        if(dir==='ease'){s+=Math.min(df,3)*13+Math.max(-2,Math.min(dsw,5))*4+Math.max(-2,Math.min(dw,5))*.6;if(df<=0&&dsw<=.2)s-=20}
        else{const dk=o.ax.comfort-ref.ax.comfort;s+=Math.min(df,3)*8+Math.max(-2,Math.min(dsw,4))*2+Math.max(-2,Math.min(dk,4))*6;if(dk<=0)s-=20;if(leadTech(o,'comfort').length)s+=4}
      }
    }
    if(o.b==='Li-Ning')s+=LN_BONUS;
    if(lineageOf(o,ref)&&dir==='same'&&!cheap)s+=6;
    if(cheap&&ref.p&&o.p)s+=Math.min(4,(ref.p-o.p)/3000);
    return {o,s};
  });
  // бонусы за «мягче», «мощнее» и т. п. могут увести выше 100 — сдвигаем всю шкалу, порядок сохраняется
  {const mx=Math.max(...list.map(x=>x.s));const sh=mx>99?mx-99:0;list.forEach(x=>{x.s=Math.max(0,Math.round(x.s-sh))})}
  const all0=list;list=fitSort(list);
  if(dir==='same'&&!cheap){const ln=list.filter(x=>lineageOf(x.o,ref)).map(x=>all0.find(y=>y.o.b===x.o.b&&y.o.m===x.o.m&&y.o.u===ref.u)||x);if(ln.length){const s0=list[0].s;ln.sort((a,b)=>b.s-a.s);ln.forEach(x=>x.s=Math.max(x.s,s0));list=[...ln,...list.filter(x=>!lineageOf(x.o,ref))]}}
  list.forEach(x=>{const o=x.o,w=[];
    // в режиме «Дешевле» сравниваем характер без коридора класса
    const dP=cheap?ub(o,'power')-ub(ref,'power'):o.ax.power-ref.ax.power,dS=cheap?ub(o,'speed')-ub(ref,'speed'):o.ax.speed-ref.ax.speed,dE=o.ax.ease-ref.ax.ease,dK=o.ax.comfort-ref.ax.comfort;
    const ln=dir==='same'&&!cheap&&lineageOf(o,ref);if(ln)w.push(/^Ближайший/.test(ln[2])?T(ln[2],ln[3]):T(ln[2]+' — по ощущениям модели близки',ln[3]+' — the two models feel close'));
    if(dir==='same')w.push(T(`Близкий характер: маховый вес ${num1(o.SW)} против ${num1(ref.SW)} кг·см², баланс ${o.bp} мм`,`Similar character: swing weight ${num1(o.SW)} vs ${num1(ref.SW)} kg·cm², balance ${o.bp} mm`));
    if(dir==='power')w.push(dP>0?T(`Мощнее на ${num1(dP)}: маховый вес ${num1(o.SW)} кг·см²`,`More power by ${num1(dP)}: swing weight ${num1(o.SW)} kg·cm²`):T('Мощь на том же уровне, остальное ближе к вашей','Same power level, closer to yours in everything else'));
    if(dir==='speed')w.push(dS>0?T(`Быстрее в руке на ${num1(dS)}${o.SW<ref.SW?`: маховый вес меньше на ${num1(ref.SW-o.SW)} кг·см²`:''}`,`Faster in the hand by ${num1(dS)}${o.SW<ref.SW?`: swing weight ${num1(ref.SW-o.SW)} kg·cm² lower`:''}`):T('Скорость на том же уровне','Same speed level'));
    if(dir==='ease'){const p=[];if(o.f&&ref.f&&o.f<ref.f)p.push(T(`стержень ${FX(o.f)} против ${FX(ref.f)}`,`${FX(o.f)} shaft vs ${FX(ref.f)}`));if(o.SW<ref.SW-.2)p.push(T(`маховый вес меньше на ${num1(ref.SW-o.SW)} кг·см²`,`swing weight ${num1(ref.SW-o.SW)} kg·cm² lower`));if(o.u>ref.u)p.push(T(`${o.u}U вместо ${ref.u}U`,`${o.u}U instead of ${ref.u}U`));w.push(p.length?T('Мягче и легче: ','Softer and lighter: ')+p.join(', '):T('Похожая требовательность к технике','Similar demands on technique'))}
    if(dir==='comfort')w.push(dK>0?T(`Бережнее к руке на ${num1(dK)}`,`Easier on the arm by ${num1(dK)}`)+(leadTech(o,'comfort').length?': '+leadTech(o,'comfort').join(T(' и ',' and ')):''):T('Комфорт на том же уровне','Same comfort level'));
    if(cheap&&o.p&&ref.p&&o.p<ref.p)w.push(T(`Дешевле на ${fmtP(ref.p-o.p)}${o.lv&&ref.lv&&o.lv<ref.lv?' — класс ниже ('+LVN(o.lv)+')':''}`,`${fmtP(ref.p-o.p)} cheaper${o.lv&&ref.lv&&o.lv<ref.lv?' — one class lower ('+LVN(o.lv)+')':''}`));
    if(!ref.custom&&o.s!==ref.s)w.push(T(`Другая серия (${o.s}) — другая рама, проверьте ощущения в руке`,`Different series (${o.s}) — a different frame, try how it feels in your hand`));
    x.why=w.slice(0,3)});
  const sp=document.getElementById('instock2').checked?[]:stockPicks(all0,list);
  const spU=new Set(sp.map(x=>x.o.b+x.o.m)),top=list.slice(0,3),more=list.slice(3).filter(x=>!spU.has(x.o.b+x.o.m)).slice(0,5);
  sp.forEach(x=>{const w=[];if(x.stockNote)w.push(x.stockNote);if(!ref.custom&&x.o.s!==ref.s)w.push(T(`Другая серия (${x.o.s}) — другая рама, проверьте ощущения в руке`,`Different series (${x.o.s}) — a different frame, try how it feels in your hand`));x.why=[...w,...(x.why||[])].slice(0,3)});
  const simL=T('похожесть с учётом пожелания','similarity, with your preference');
  out.innerHTML=`<div class="profile"><h2>${T('Характер','Character')}: ${label(ref)} ${ref.u}U${ref.custom?T(' (по вашим данным)',' (from your data)'):ref.site?'':T(' · нет в магазине',' · not sold in store')}</h2>${AXN().map(([k,l])=>`<span class="pill">${l} ${num1(ref.ax[k])}</span>`).join('')}</div>
  ${!ref.custom&&ref.hasT?essence(ref):''}
  <div class="legend"><span><i class="lg-fill"></i>${T('характер ракетки','racket character')}</span><span><i class="lg-tgt"></i>${T('желаемый характер','target character')}</span></div>
  ${ref.custom?`<p class="topnote">${T('Характер рассчитан по введённым данным','Character calculated from the data you entered')}${ref.bpGuess?T(': точный баланс в мм не указан, взято типичное значение',': balance in mm not given, a typical value is used'):''}${ref.swEst?T('; маховый вес оценён по весу и балансу','; swing weight estimated from weight and balance'):''}. ${T('Чем больше вы укажете, тем точнее подбор.','The more you enter, the more accurate the match.')}</p>`:''}
  ${fixCls?`<p class="topnote">${T(`Показываем только класс «${LVN(fixCls)}». «Оптимальную» — того же класса, что и ваша ракетка, «Дешевле» — похожие из класса ниже.`,`Showing ${LVN(fixCls)} rackets only. “Best match” keeps the class of your racket, “Cheaper” looks one class lower.`)}</p>`:''}
  ${top.length?`<div class="cards">${top.map((x,i)=>cardHTML(x,t,i,simL)).join('')}</div>`:'<div class="panel empty">'+T('Ничего не нашлось — снимите фильтр по наличию или бренду.','Nothing found — remove the stock or brand filter.')+'</div>'}
  ${stockHTML(sp,t,simL)}
  ${more.length?`<div class="more"><h4>${T('Ещё варианты','More options')}</h4><div class="rowlist">${more.map(rowHTML).join('')}</div></div>`:''}`;
}
let mode='quiz';
function render(){document.getElementById('tune').hidden=false;mode==='quiz'?renderQuiz():renderLike()}
function setMode(m){mode=m;document.getElementById('m-quiz').setAttribute('aria-pressed',m==='quiz');document.getElementById('m-like').setAttribute('aria-pressed',m==='like');document.getElementById('quiz').hidden=m!=='quiz';document.getElementById('like').hidden=m!=='like';render()}
document.getElementById('m-quiz').onclick=()=>setMode('quiz');
document.getElementById('m-like').onclick=()=>setMode('like');
document.getElementById('quiz').addEventListener('change',()=>{if(!started){started=true;goal('podbor_start',{mode:'quiz'})}render();goal('podbor_result',{mode:'quiz',lvl:val('lvl'),who:val('who'),tier:val('tier')})});
document.getElementById('like').addEventListener('change',()=>{if(!started){started=true;goal('podbor_start',{mode:'like'})}render()});
out.addEventListener('click',e=>{const g=e.target.closest('[data-goal]');if(g)goal(g.dataset.goal,{id:g.dataset.id,mode});const b=e.target.closest('[data-like]');if(!b)return;goal('podbor_like',{id:b.dataset.like});document.getElementById('dir-same').checked=true;setMode('like');setRef(b.dataset.like);document.querySelector('.quiz').scrollIntoView({behavior:'smooth',block:'start'})});
function route(){const c=location.hash==='#concept';document.getElementById('v-demo').hidden=c;document.getElementById('v-concept').hidden=!c;document.getElementById('t-demo').setAttribute('aria-current',c?'false':'page');document.getElementById('t-concept').setAttribute('aria-current',c?'page':'false')}
// ---- перевод статичного текста страницы: русский — в разметке, английский — здесь
const I18N={
 eyebrow:'Uniqsport · racket finder',
 h1:'Find a racket by its character',
 lead:'Built on the real Uniqsport catalogue: __NSITE__ Li-Ning and Yonex rackets, __NMEAS__ of them measured on the store’s swing-weight machine and __NTECH__ with their technologies broken down. Another __NCAT__ Li-Ning, Yonex and Victor rackets come from the manufacturers’ catalogues — you can find them in search and on the racket maps. The finder looks not only at weight, balance and stiffness but at how a racket is built: swing weight, head weight, and frame and shaft technologies.',
 t_demo:'Racket finder',t_concept:'How it works',
 map_title:'Racket maps · Li-Ning · Yonex · Victor',map_char:'Similarity: power · control · speed',map_feel:'Feel in the hand: weight and stiffness',
 u_any:'Any weight',u_6:'6U and lighter',map_site:'Sold in store only',map_old:'Hide older models',v_plot:'Chart',v_tbl:'List',
 m_quiz:'By questions',m_like:'From my racket',
 q_who:'Who is the racket for?',q_lvl:'How long have you been playing?',q_tier:'Which racket are you looking for?',
 h_tier:'Best for my game — matching across all classes for your level. Or choose a class: entry-level, recreational, semi-pro, professional.',
 q_fmt:'What do you play most?',q_style:'Your game',q_swing:'How fast is your swing?',
 h_swing:'Fast — you hit with the wrist and the shuttle easily reaches the back line.',
 q_arm:'Does your arm or elbow get tired?',q_brand:'Brand',instock:'In stock only',
 ref_q:'Your racket, or one you like',to_manual:'My racket isn’t listed — I’ll enter the specs',man_h:'Your racket’s specs',
 man_name:'Name (optional)',man_u:'Weight',man_bp:'Balance, mm',man_p:'Price, ₽',man_sw:'Swing weight, kg·cm² (if measured)',
 man_bt:'Balance, if you don’t know the mm',man_f:'Shaft stiffness',man_s:'Racket character',to_search:'Back to catalogue search',
 q_dir:'What do you want?',h_ltier:'Best match — the same class as your racket. Cheaper — similar rackets one class lower. Or choose a class yourself.',
 h_like:'We look for rackets with a similar character from other series and brands, shifted in the direction you choose.',
 a_sections:'Sections',a_map:'Map',a_close:'Close',a_weight:'Weight',a_find:'Find a model',a_view:'View',a_modes:'Matching mode',
 a_refph:'e.g. AXForce 80 or Astrox 88D',a_manph:'e.g. Victor Thruster K 9900',a_ifknown:'if known',a_forcheap:'for “Cheaper”',a_eg85:'e.g. 85'
};
const TITLE={ru:document.title||'Подборщик ракеток Uniqsport',en:'Uniqsport Racket Finder'};
const RU_HTML=new Map(),RU_ATTR=new Map();
document.querySelectorAll('[data-i18n]').forEach(el=>RU_HTML.set(el,el.innerHTML));
document.querySelectorAll('[data-i18n-attr]').forEach(el=>RU_ATTR.set(el,el.dataset.i18nAttr.split(';').map(x=>{const [a,k]=x.split(':');return [a,k,el.getAttribute(a)]})));
function applyLang(){
  const en=LANG==='en';document.documentElement.lang=LANG;document.title=TITLE[LANG];
  RU_HTML.forEach((ru,el)=>{const k=el.dataset.i18n;el.innerHTML=en&&I18N[k]?I18N[k]:ru});
  RU_ATTR.forEach((l,el)=>l.forEach(([a,k,ru])=>el.setAttribute(a,en&&I18N[k]?I18N[k]:ru)));
  document.querySelectorAll('.chips').forEach(box=>{const q=box.dataset.q;box.querySelectorAll('.chip span').forEach((sp,i)=>{const o=Q[q][i];if(o)sp.textContent=T(o[1],o[2]||o[1])})});
  document.querySelectorAll('#sliders .sl>span').forEach((sp,i)=>{sp.textContent=AXN()[i][1]});
  document.querySelectorAll('[data-setlang]').forEach(b=>b.setAttribute('aria-pressed',b.dataset.setlang===LANG));
  if(refMode==='catalog'&&refId)picked.textContent=pickedTxt();
}
function setLang(l){
  if(l===LANG)return;
  if(PAGE_LANG!=='auto'&&ALT_URL[l]){location.href=ALT_URL[l];return}
  LANG=l;try{localStorage.setItem('podbor_lang',l)}catch(e){}
  goal('podbor_lang',{to:l});applyLang();render();if(window.__mapRedraw)window.__mapRedraw();
}
document.querySelectorAll('[data-setlang]').forEach(b=>b.addEventListener('click',()=>setLang(b.dataset.setlang)));
applyLang();
window.addEventListener('hashchange',route);route();{const d=SITE.find(o=>o.m==='AXForce 80'&&o.u===4);if(d)setRef(d.uid)}render();

// ---- карты ракеток: «сходство» (атака · скорость · контроль, как у Li-Ning) и «ощущение в руке» (маховый вес × жёсткость) ----
{
const BR=['Li-Ning','Yonex','Victor'],COL={'Li-Ning':'var(--s-ln)','Yonex':'var(--s-yx)','Victor':'var(--s-vc)'};
const $=id=>document.getElementById(id);
const dlg=$('mapdlg'),svg=$('map-svg'),tip=$('map-tip');
const MAP=R.filter(o=>BR.includes(o.b));
const stf=o=>{const f=o.f||3;return o.fe?(.5*(f-1)/5+.5*(o.fe-1)/4):(f-1)/5};
const hh=s=>{let h=0;for(const c of s)h=(h*31+c.charCodeAt(0))%997;return h/997-.5};
MAP.forEach(o=>{o.mx=Math.max(0,Math.min(1,stf(o)+.08*(o.cx||0)+.05*hh(o.b+o.m+o.u)));o.my=o.SW;
  o.kx=o.ax.speed-o.ax.power;o.ky=o.ax.control});
const W_=1000,H_=540,PL=64,PR=18,PT=18,PB=52;
let swLo=70,swHi=96,kind='char',view='plot',sel=null;
const X=v=>PL+v*(W_-PL-PR),Y=v=>H_-PB-(v-swLo)/(swHi-swLo)*(H_-PT-PB);
// карта сходства: центр и масштаб
// карта сходства как у Yonex 2026: слева «мощь», справа «скорость», в пересечении — контроль и универсальные; выше — больше контроля
const CX=520,KSX=52,CB=490,CT=80;
let kxMax=4,cLo=1,cHi=10;
const kxT=v=>v;
const KX=v=>CX+kxT(v)/kxT(kxMax)*(W_-PR-CX-40),KY=v=>CB-(v-cLo)/(cHi-cLo)*(CB-CT);
const rOf=o=>kind==='char'?({1:3,2:4.5,3:6,4:7.5}[o.lv]||5):5;
const shape=(o,x,y,r)=>{x=+x;y=+y;const c=COL[o.b],fill=o.swEst?'var(--surface)':c,sw=o.swEst?2:1.5,st=o.swEst?c:'var(--surface)';
  if(o.b==='Yonex')return `<rect x="${x-r+.5}" y="${y-r+.5}" width="${2*r-1}" height="${2*r-1}" rx="1.5" fill="${fill}" stroke="${st}" stroke-width="${sw}"/>`;
  if(o.b==='Victor')return `<path d="M${x} ${y-r-1}L${x+r+1} ${y+r}L${x-r-1} ${y+r}Z" fill="${fill}" stroke="${st}" stroke-width="${sw}" stroke-linejoin="round"/>`;
  return `<circle cx="${x}" cy="${y}" r="${r}" fill="${fill}" stroke="${st}" stroke-width="${sw}"/>`};
const TXT_RU={
 char:{desc:'Как ракетка играет — по образцу диаграммы Yonex 2026. Слева мощные ракетки, справа скоростные, в пересечении — контроль и универсальные (у Yonex это одно и то же — серия Arcsaber). По горизонтали — чего у ракетки больше: мощи или скорости; по вертикали — контроль. Размер точки — класс. Нажмите на ракетку — покажем самые похожие на неё.',
   note:'Оценки — те же, по которым работает подбор: замеры, технологии, характер серии и класс ракетки. У каждого класса свой коридор: начальная — 1–5, любительская — 2,5–6,5, полупрофессиональная — 4–8,5, профессиональная — 5,5–10, поэтому профессиональная ракетка не слабее начальной ни в мощи, ни в скорости, ни в контроле. Похожие ищутся только среди ракеток того же класса: прежде всего по мощи, скорости и контролю, с поправкой на ощущение в руке.'},
 feel:{desc:'Как ракетка ощущается в руке. По горизонтали — жёсткость (стержень и ощущение от удара), по вертикали — маховый вес: насколько ракетка тяжела в замахе. Рядом стоят ракетки, которые одинаково тяжелы в замахе и одинаково жёсткие. Это не значит, что они одинаково играют: мощь, скорость и контроль зависят ещё от распределения веса, формы и аэродинамики обода, толщины стержня и технологий. Как ракетки играют — на карте сходства.',
   note:'Положение считается по нашим данным: замеры на станке, а если их нет — вес, баланс и жёсткость из каталогов производителей. Позиции в фирменных диаграммах Li-Ning, Yonex и Victor показаны в подсказке к точке.'}};
const TXT_EN={
 char:{desc:'How a racket plays, modelled on the Yonex 2026 chart. Power rackets on the left, speed rackets on the right, control and all-round rackets where they overlap (for Yonex these are the same thing — the Arcsaber series). Horizontally: whether the racket has more power or more speed; vertically: control. Dot size shows the class. Tap a racket to see the most similar ones.',
   note:'The scores are the same ones the finder uses: measurements, technologies, series character and racket class. Each class has its own range: entry-level 1–5, recreational 2.5–6.5, semi-pro 4–8.5, professional 5.5–10, so a professional racket is never weaker than an entry-level one in power, speed or control. Similar rackets are searched within the same class only: mainly by power, speed and control, adjusted for feel in the hand.'},
 feel:{desc:'How a racket feels in the hand. Horizontally: stiffness (shaft and feel on impact); vertically: swing weight — how heavy the racket is to swing. Rackets next to each other are equally heavy to swing and equally stiff. That does not mean they play the same: power, speed and control also depend on weight distribution, frame shape and aerodynamics, shaft thickness and technologies. For how rackets play, see the similarity map.',
   note:'Positions come from our own data: measurements on the machine, or, where there are none, weight, balance and stiffness from the manufacturers’ catalogues. Positions on the official Li-Ning, Yonex and Victor charts are shown in the tooltip for each dot.'}};
const TXT=()=>LANG==='en'?TXT_EN:TXT_RU;
const legend=()=>BR.map(b=>`<span><svg viewBox="-8 -8 16 16">${shape({b,swEst:false},0,0,5)}</svg>${b}</span>`).join('')+
  `<span><svg viewBox="-8 -8 16 16"><circle r="5" fill="var(--muted)" stroke="var(--surface)"/></svg>${T('измерено в Uniqsport','measured by Uniqsport')}</span><span><svg viewBox="-8 -8 16 16"><circle r="5" fill="var(--surface)" stroke="var(--muted)" stroke-width="2"/></svg>${T('измерения по каталогу','catalogue specs')}</span>`+
  (kind==='char'?`<span><svg viewBox="-12 -9 24 18" style="width:24px;height:18px"><circle r="2.5" cx="-10" fill="var(--muted)"/><circle r="3.5" cx="-4" fill="var(--muted)"/><circle r="4.5" cx="3" fill="var(--muted)"/><circle r="5.5" cx="11" fill="var(--muted)"/></svg>${T('размер точки — класс: начальная · любительская · полупрофи · профессиональная','dot size — class: entry-level · recreational · semi-pro · professional')}</span>`:'');
const FE5_RU=['','мягкое','мягковатое','среднее','жестковатое','жёсткое'],FE5_EN=['','soft','slightly soft','medium','slightly firm','firm'];
const FE5=i=>(LANG==='en'?FE5_EN:FE5_RU)[i];
const n1=v=>num1(v);
const chartTxt=o=>{if(o.cx==null)return '';const sx=o.cx>.33?T('жёстче','stiffer'):o.cx<-.33?T('мягче','softer'):T('посередине по жёсткости','mid-range in stiffness'),sy=o.cy>.33?T('выше (тяжелее голова)','higher (heavier head)'):o.cy<-.33?T('ниже (легче)','lower (lighter)'):T('по центру по высоте','in the middle vertically');return T(`На диаграмме ${o.cbr}: ${sx}, ${sy}`,`On the ${o.cbr} chart: ${sx}, ${sy}`)};
const st=()=>({br:new Set([...document.querySelectorAll('#map-f [data-br]')].filter(i=>i.checked).map(i=>i.dataset.br)),u:$('map-u').value,site:$('map-site').checked,old:$('map-old').checked,q:$('map-q').value.trim().toLowerCase()});
const uOk=(o,u)=>!u||(u==='6'?o.u>=6:o.u===+u);
// сходство — как в режиме «Похожую» (характер + ощущение в руке)
const physD=(o,r)=>Math.abs(o.SW-r.SW)/1.0+Math.abs(o.H-r.H)/0.8+Math.abs(o.bp-r.bp)/5+Math.abs(o.W-r.W)/3+((o.f&&r.f)?Math.abs(o.f-r.f)*.4:.4);
// главное — характер (мощь, скорость, контроль): разница в 3 балла по одной оси — уже другая ракетка; ощущение в руке — поправка
const charD=(o,r)=>Math.sqrt(((o.ax.power-r.ax.power)**2+(o.ax.speed-r.ax.speed)**2+(o.ax.control-r.ax.control)**2)/3);
const simOf=(o,r)=>Math.max(0,Math.min(99,Math.round(100-charD(o,r)*15-Math.abs(o.ax.ease-r.ax.ease)*1.5-Math.abs(o.ax.comfort-r.ax.comfort)-physD(o,r)*1.5)));
// похожими считаем только ракетки того же уровня: топ — с топом, средние — со средними, начальные — с начальными
function similar(ref,L){return L.filter(o=>!(o.b===ref.b&&o.m===ref.m)&&(!ref.lv||o.lv===ref.lv)).map(o=>({o,s:simOf(o,ref)})).sort((a,b)=>b.s-a.s).slice(0,6)}
let curL=[];
function draw(){
  const S=st();const qm=o=>S.q&&(o.b+' '+o.m).toLowerCase().includes(S.q);
  const L=MAP.filter(o=>S.br.has(o.b)&&(uOk(o,S.u)||qm(o))&&(!S.site||o.site)&&(!S.old||!(o.old||/^(Tectonic|Aeronaut|Calibar|TurboCharging|AirStream)$/.test(o.s))));
  curL=L;const hit=o=>!S.q||(o.b+' '+o.m).toLowerCase().includes(S.q);
  $('map-cnt').textContent=T(`Показано ракеток: ${L.length}`,`Rackets shown: ${L.length}`);
  $('map-desc').textContent=TXT()[kind].desc;$('map-note').textContent=TXT()[kind].note;$('map-legend').innerHTML=legend();
  $('map-k-char').setAttribute('aria-selected',kind==='char');$('map-k-feel').setAttribute('aria-selected',kind==='feel');
  svg.setAttribute('viewBox','0 0 1000 540');
  svg.setAttribute('aria-label',kind==='char'?T('Карта сходства: атака, скорость, контроль','Similarity map: power, speed, control'):T('Карта ощущений: жёсткость и маховый вес','Feel map: stiffness and swing weight'));
  const selO=sel&&L.find(o=>o.uid===sel);const simL=selO?similar(selO,L):[];const simU=new Set(simL.map(x=>x.o.uid));
  let g='';const pos=o=>kind==='char'?[KX(o.kx),KY(o.ky)]:[X(o.mx),Y(o.my)];
  if(kind==='char'){
    if(L.length){const xs=L.map(o=>Math.abs(o.kx)).sort((a,b)=>a-b);kxMax=Math.max(1,xs[Math.floor(xs.length*.98)]||1);cLo=Math.max(1,Math.floor(Math.min(...L.map(o=>o.ky))-.3));cHi=Math.min(10,Math.ceil(Math.max(...L.map(o=>o.ky))))}
    for(let v=cLo;v<=cHi;v++){g+=`<line class="grid" x1="${PL}" x2="${W_-PR}" y1="${KY(v)}" y2="${KY(v)}"/><text class="axis" x="${PL-8}" y="${KY(v)+4}" text-anchor="end">${v}</text>`}
    const r=(CB-CT)/2+30,dx=200,cy=(CB+CT)/2;
    g+=`<circle class="circ" cx="${CX-dx}" cy="${cy}" r="${r}" fill-opacity=".45"/><circle class="circ" cx="${CX+dx}" cy="${cy}" r="${r}" fill-opacity=".45"/>`;
    g+=`<text class="vlab" x="${CX-dx-150}" y="26" text-anchor="middle">${T('Мощь','Power')}</text><text class="vsub" x="${CX-dx-150}" y="44" text-anchor="middle">AXForce · Astrox · Thruster</text>`;
    g+=`<text class="vlab" x="${CX+dx+150}" y="26" text-anchor="middle">${T('Скорость','Speed')}</text><text class="vsub" x="${CX+dx+150}" y="44" text-anchor="middle">BladeX · Nanoflare · Auraspeed</text>`;
    g+=`<text class="vlab" x="${CX}" y="26" text-anchor="middle">${T('Контроль · универсальные','Control · all-round')}</text><text class="vsub" x="${CX}" y="44" text-anchor="middle">Halbertec · Arcsaber · DriveX</text>`;
    g+=`<text class="axis" transform="translate(16 ${(CT+CB)/2}) rotate(-90)" text-anchor="middle">${T('Контроль →','Control →')}</text>`;
    g+=`<text class="axis" x="${PL+6}" y="${H_-12}">${T('← мощнее, чем быстрая','← more power than speed')}</text><text class="axis" x="${W_-PR-6}" y="${H_-12}" text-anchor="end">${T('быстрее, чем мощная →','more speed than power →')}</text>`;
  }else{
    if(L.length){swLo=Math.floor(Math.min(...L.map(o=>o.SW))-.5);swHi=Math.ceil(Math.max(...L.map(o=>o.SW))+.5);if(swHi-swLo<6){swHi+=3;swLo-=3}}
    const stp=swHi-swLo>14?2:1;for(let v=swLo;v<=swHi;v++){if(v%stp)continue;g+=`<line class="grid" x1="${PL}" x2="${W_-PR}" y1="${Y(v)}" y2="${Y(v)}"/><text class="axis" x="${PL-8}" y="${Y(v)+4}" text-anchor="end">${v}</text>`}
    [[0,T('гибкая','flexible')],[.4,T('средняя','medium')],[.8,T('жёсткая','stiff')],[1,T('очень жёсткая','extra stiff')]].forEach(([v,t])=>{g+=`<line class="grid" x1="${X(v)}" x2="${X(v)}" y1="${PT}" y2="${H_-PB}"/><text class="axis" x="${X(v)}" y="${H_-PB+18}" text-anchor="${v===1?'end':v===0?'start':'middle'}">${t}</text>`});
    g+=`<text class="axis" x="${(PL+W_-PR)/2}" y="${H_-8}" text-anchor="middle">${T('Жёсткость: стержень и ощущение от удара →','Stiffness: shaft and feel on impact →')}</text>`;
    g+=`<text class="axis" transform="translate(16 ${(PT+H_-PB)/2}) rotate(-90)" text-anchor="middle">${T('Маховый вес, кг·см² →','Swing weight, kg·cm² →')}</text>`;
    g+=`<text class="zone" x="${W_-PR-6}" y="${PT+16}" text-anchor="end">${T('Тяжёлые в замахе и жёсткие','Heavy to swing and stiff')}</text><text class="zone" x="${W_-PR-6}" y="${H_-PB-10}" text-anchor="end">${T('Лёгкие в замахе и жёсткие','Light to swing and stiff')}</text><text class="zone" x="${PL+8}" y="${H_-PB-10}">${T('Лёгкие и мягкие: прощают ошибки','Light and soft: forgiving')}</text><text class="zone" x="${PL+8}" y="${PT+16}">${T('Тяжёлые в замахе и мягкие','Heavy to swing and soft')}</text>`;
  }
  if(selO){const [x0,y0]=pos(selO);g+=simL.map(x=>{const [x1,y1]=pos(x.o);return `<line class="link" x1="${x0}" y1="${y0}" x2="${x1}" y2="${y1}"/>`}).join('')}
  const order=[...L].sort((a,b)=>(hit(a)-hit(b))||(rOf(b)-rOf(a))||(b.swEst-a.swEst));
  const dimO=o=>!hit(o)||(selO&&o.uid!==selO.uid&&!simU.has(o.uid));
  g+=order.map(o=>{const [x,y]=pos(o);return `<g class="pt${dimO(o)?' dim':''}${selO&&o.uid===selO.uid?' hl':''}" data-u="${o.uid}" tabindex="0" role="button" aria-label="${o.b} ${o.m} ${o.u}U">${shape(o,x.toFixed(1),y.toFixed(1),rOf(o))}</g>`}).join('');
  const labs=selO?[selO]:(S.q?L.filter(hit):[]);
  if(labs.length&&labs.length<=12)g+=labs.map(o=>{const [x,y]=pos(o);const r=x>W_*.7;return `<text class="lbl" x="${x+(r?-10:10)}" y="${y-9}" text-anchor="${r?'end':'start'}">${o.m} ${o.u}U</text>`}).join('');
  if(selO)g+=simL.map((x,i)=>{const [px,py]=pos(x.o);return `<text class="lbl num" x="${px+8}" y="${py+13}">${i+1}</text>`}).join('');
  svg.innerHTML=g;
  // похожие
  const sim=$('map-sim');
  if(selO&&view==='plot'){sim.hidden=false;sim.innerHTML=`<h3>${T('Похожи на','Similar to')} ${selO.b} ${selO.m} ${selO.u}U ${selO.site?`· <a href="${url(selO)}" target="_blank" rel="noopener">${T('карточка в магазине','view in store')}</a>`:''}</h3><ol>${simL.map(x=>`<li><button type="button" class="lnk" data-u="${x.o.uid}">${x.o.b} ${x.o.m} ${x.o.u}U</button> <span class="pct">${x.s}%</span> <span class="bars">· ${T('мощь','power')} ${n1(x.o.ax.power)} · ${T('скорость','speed')} ${n1(x.o.ax.speed)} · ${T('контроль','control')} ${n1(x.o.ax.control)}${x.o.site?'':T(' · нет в магазине',' · not sold in store')}</span></li>`).join('')}</ol>`}
  else sim.hidden=true;
  // список
  const rows=[...L].filter(hit).sort((a,b)=>a.b.localeCompare(b.b)||a.m.localeCompare(b.m)||a.u-b.u);
  $('map-tbl').innerHTML=`<table><thead><tr><th>${T('Бренд','Brand')}</th><th>${T('Модель','Model')}</th><th>${T('Вес','Weight')}</th><th class="num">${T('Мощь','Power')}</th><th class="num">${T('Скорость','Speed')}</th><th class="num">${T('Контроль','Control')}</th><th>${T('Стержень','Shaft')}</th><th>${T('Ощущение','Feel')}</th><th class="num">${T('Баланс, мм','Balance, mm')}</th><th class="num">${T('Маховый вес','Swing weight')}</th><th>${T('Данные','Data')}</th><th>${T('В магазине','In store')}</th></tr></thead><tbody>${rows.map(o=>`<tr><td>${o.b}</td><td>${o.site?`<a href="${url(o)}" target="_blank" rel="noopener">${o.m}</a>`:o.m}${o.old?' <span class="src">'+T('(прошлых лет)','(older model)')+'</span>':''}</td><td>${o.u}U</td><td class="num">${n1(o.ax.power)}</td><td class="num">${n1(o.ax.speed)}</td><td class="num">${n1(o.ax.control)}</td><td>${o.f?FX(o.f):T('нет данных','no data')}</td><td>${o.fe?FE5(o.fe):'—'}</td><td class="num">${o.bp}</td><td class="num">${n1(o.SW)}${o.swEst?'*':''}</td><td>${o.swEst?T('по каталогу','catalogue'):'Uniqsport'}</td><td>${o.site?(o.q===1?T('в наличии','in stock'):T('есть карточка','listed')):'—'}</td></tr>`).join('')}</tbody></table><p class="map-note">${T('* маховый вес рассчитан по весу и балансу','* swing weight calculated from weight and balance')}</p>`;
  $('map-b').hidden=view!=='plot';$('map-tbl').hidden=view!=='tbl';
}
const byU=u=>MAP.find(o=>o.uid===u);
function showTip(o,ev){
  const b=$('map-b').getBoundingClientRect();
  const ch=T(`Мощь ${n1(o.ax.power)} · скорость ${n1(o.ax.speed)} · контроль ${n1(o.ax.control)}`,`Power ${n1(o.ax.power)} · speed ${n1(o.ax.speed)} · control ${n1(o.ax.control)}`);
  const fl=T(`Маховый вес ${n1(o.SW)} кг·см²${o.swEst?' (расчёт)':''} · баланс ${o.bp} мм<br>Стержень: ${o.f?FX(o.f):'нет данных'}${o.fe?' · ощущение: '+FE5(o.fe):''}`,`Swing weight ${n1(o.SW)} kg·cm²${o.swEst?' (calculated)':''} · balance ${o.bp} mm<br>Shaft: ${o.f?FX(o.f):'no data'}${o.fe?' · feel: '+FE5(o.fe):''}`);
  tip.innerHTML=`<b>${o.b} ${o.m} ${o.u}U</b>${o.lv?' · '+LVN(o.lv):''}<br>${kind==='char'?ch+'<br>'+fl:fl+'<br>'+ch}<br><span class="src">${o.swEst?T('Измерения по каталогу','Catalogue specs'):T('Измерено в Uniqsport','Measured by Uniqsport')}${o.site?T(' · есть в магазине',' · sold in store'):''}${o.old?T(' · модель прошлых лет',' · older model'):''}</span>${kind==='feel'&&chartTxt(o)?'<br><span class="src">'+chartTxt(o)+'</span>':''}`;
  tip.hidden=false;const x=ev.clientX-b.left,y=ev.clientY-b.top;const tw=tip.offsetWidth,th=tip.offsetHeight;
  tip.style.left=Math.max(4,Math.min(b.width-tw-4,x+12))+'px';tip.style.top=Math.max(4,(y-th-12<0?y+14:y-th-12))+'px';
}
svg.addEventListener('pointermove',e=>{const g=e.target.closest('.pt');if(!g){tip.hidden=true;return}showTip(byU(g.dataset.u),e)});
svg.addEventListener('pointerleave',()=>{tip.hidden=true});
svg.addEventListener('click',e=>{const g=e.target.closest('.pt');if(!g){if(sel){sel=null;draw()}return}const o=byU(g.dataset.u);sel=sel===o.uid?null:o.uid;draw();showTip(o,e)});
$('map-sim').addEventListener('click',e=>{const b=e.target.closest('button.lnk');if(!b)return;sel=b.dataset.u;draw()});
$('map-q').addEventListener('input',()=>{sel=null});
$('map-f').addEventListener('input',draw);
const setView=v=>{view=v;$('map-v-plot').setAttribute('aria-pressed',v==='plot');$('map-v-tbl').setAttribute('aria-pressed',v==='tbl');draw()};
$('map-v-plot').onclick=()=>setView('plot');$('map-v-tbl').onclick=()=>setView('tbl');
const setKind=k=>{kind=k;tip.hidden=true;if(view!=='plot')view='plot',$('map-v-plot').setAttribute('aria-pressed','true'),$('map-v-tbl').setAttribute('aria-pressed','false');draw()};
$('map-k-char').onclick=()=>setKind('char');$('map-k-feel').onclick=()=>setKind('feel');
$('mapbtn').onclick=()=>{draw();dlg.showModal();goal('map_open')};
window.__mapRedraw=()=>{if(dlg.open)draw()};
$('map-close').onclick=()=>dlg.close();
dlg.addEventListener('click',e=>{if(e.target===dlg)dlg.close()});
}
