import re, math
sql=open('db/upgrade_v2.sql').read(); orig=open('db/walisongo-komputer.sql').read()
def split(r):
    out=[];cur='';q=False
    for ch in r:
        if ch=="'": q=not q
        if ch==',' and not q: out.append(cur.strip());cur=''
        else: cur+=ch
    out.append(cur.strip());return out
def val(x): return None if x=='NULL' else (x.strip("'") if x.startswith("'") else int(x))
P={}
for r in re.findall(r"^\('((?:CPU|MOB|GPU|RAM|SSD|PSU|CLR|CAS)\d\d)',(.*)\),?;?$",orig,re.M):
    v=[val(x) for x in split("'"+r[0]+"',"+r[1])]
    k,cat,_,brand,nm,spec,harga,sock,tr,w=v[:10]
    P[k]=dict(kode_part=k,kategori=cat,brand=brand,nama_part=nm,harga=harga,socket=sock,tipe_ram=tr,watt_tdp=w,skor_gaming=0,skor_kreator=0,kapasitas=0,form_factor=None,igpu=0)
for m in re.finditer(r"UPDATE komponen SET (.*) WHERE kode_part='(\w+)';",sql):
    for a in re.findall(r"(\w+)=('[^']*'|\d+)",m.group(1)):
        if a[0] in P[m.group(2)] : P[m.group(2)][a[0]]=val(a[1])
blk=sql[sql.index("INSERT INTO komponen"):]
cols=[c.strip() for c in re.search(r'\((.*?)\)\s*VALUES',blk,re.S).group(1).replace('\n',' ').split(',')]
for r in re.findall(r"^\((.*)\),?;?$",blk,re.M):
    d=dict(zip(cols,[val(x) for x in split(r)])); P[d['kode_part']]=d
CATS=['CPU','Motherboard','GPU','RAM','Storage','Cooler','Casing','PSU']
parts={c:[p for p in P.values() if p['kategori']==c] for c in CATS}
ALOK={'C':dict(CPU=22,Motherboard=12,GPU=28,RAM=10,Storage=9,PSU=8,Cooler=4,Casing=7),
 'G':dict(CPU=17,Motherboard=11,GPU=36,RAM=7,Storage=8,PSU=8,Cooler=4,Casing=9),
 'D':dict(CPU=26,Motherboard=12,GPU=26,RAM=12,Storage=8,PSU=8,Cooler=4,Casing=4),
 'O':dict(CPU=30,Motherboard=16,GPU=0,RAM=14,Storage=14,PSU=10,Cooler=6,Casing=10)}
WL={'C':(8,32,1000,'skor_kreator'),'G':(8,16,500,'skor_gaming'),'D':(12,32,1000,'skor_kreator'),'O':(0,16,500,'skor_kreator')}
PR={'performa':dict(CPU=1.15,GPU=1.15,Casing=.8,Cooler=.8),'upgrade':dict(Motherboard=1.4,PSU=1.5,CPU=.95),
    'efisien':dict(Cooler=1.8,PSU=1.3,Casing=1.2,GPU=.9),'hemat':dict(Casing=.6,Cooler=.7,Motherboard=.9)}
SW={'Premiere':dict(CPU=3,RAM=3),'After':dict(RAM=4,CPU=2),'Blender':dict(GPU=12,CPU=4),'AutoCAD':dict(CPU=5,RAM=2),'Game':dict(GPU=12),'VS Code':dict(CPU=4,RAM=3)}
def bok(cat,p,pref):
    if pref=='intel-nvidia' and cat in('CPU','GPU'): return p['brand']==('Intel' if cat=='CPU' else 'NVIDIA')
    if pref=='amd' and cat in('CPU','GPU'): return p['brand']=='AMD'
    return True
def totals(ch):
    w=sum(p['watt_tdp'] for c,p in ch.items() if c!='PSU'); return sum(p['harga'] for p in ch.values()),w
def check(ch,col):
    e=[]
    if 'CPU' in ch and 'Motherboard' in ch and ch['CPU']['socket']!=ch['Motherboard']['socket']: e.append('socket')
    if 'Motherboard' in ch and 'RAM' in ch and ch['Motherboard']['tipe_ram']!=ch['RAM']['tipe_ram']: e.append('ram')
    if 'Motherboard' in ch and 'Casing' in ch and ch['Motherboard']['form_factor']=='ATX' and ch['Casing']['form_factor']!='ATX': e.append('case')
    if 'CPU' in ch and 'Cooler' in ch and ch['Cooler']['kapasitas']<ch['CPU']['watt_tdp']: e.append('cooler')
    if 'PSU' in ch and ch['PSU']['watt_tdp']<totals(ch)[1]*1.25: e.append('psu')
    return e
def build(wk,budget,prio,brand,cats,sw):
    mv,mr,ms,col=WL[wk]
    if budget>=18e6: pass
    else: mv=min(mv,8)
    if budget<8e6: mr=min(mr,16); ms=min(ms,500)
    w={c:ALOK[wk][c] for c in cats}
    for s in sw:
        for k,b in SW.items():
            if k.lower() in s.lower():
                for c,v in b.items():
                    if c in w: w[c]+=v
    for c in w: w[c]*=PR[prio].get(c,1)
    need=False
    if 'GPU' in w and w['GPU']<5 and len(w)>1: del w['GPU']; need=True
    sw_=sum(w.values()); remain=budget; remW=sw_; ch={}
    for cat in CATS:
        if cat not in w: continue
        cap=remain*w[cat]/remW; l=list(parts[cat])
        def soft(l,f):
            r=[p for p in l if f(p)]; return r or l
        if cat in('CPU','GPU'): l=soft(l,lambda p:bok(cat,p,brand))
        if cat=='CPU' and need: l=soft(l,lambda p:p['igpu']==1)
        if cat=='Motherboard' and 'CPU' in ch: l=soft(l,lambda p:p['socket']==ch['CPU']['socket'])
        if cat=='GPU': l=soft(l,lambda p:p['kapasitas']>=mv)
        if cat=='RAM':
            if 'Motherboard' in ch: l=soft(l,lambda p:p['tipe_ram']==ch['Motherboard']['tipe_ram'])
            l=soft(l,lambda p:p['kapasitas']>=mr)
        if cat=='Storage': l=soft(l,lambda p:p['kapasitas']>=ms)
        if cat=='Cooler' and 'CPU' in ch: l=soft(l,lambda p:p['kapasitas']>=ch['CPU']['watt_tdp'])
        if cat=='Casing' and 'Motherboard' in ch and ch['Motherboard']['form_factor']=='ATX': l=soft(l,lambda p:p['form_factor']=='ATX')
        if cat=='PSU':
            nd=math.ceil(sum(p['watt_tdp'] for c,p in ch.items())*(1.6 if prio=='upgrade' else 1.4)/50)*50
            l=soft(l,lambda p:p['watt_tdp']>=nd)
            lim=max(nd*(1.5 if prio in('upgrade','efisien') else 1.3),min(p['watt_tdp'] for p in l)); l=[p for p in l if p['watt_tdp']<=lim]
        inn=[p for p in l if p['harga']<=cap]
        if not inn: pick=sorted(l,key=lambda p:p['harga'])[0]
        elif prio=='hemat':
            top=max(p[col] for p in inn); inn=[p for p in inn if p[col]>=.7*top]
            pick=sorted(inn,key=lambda p:-p[col]/p['harga'])[0]
        else: pick=sorted(inn,key=lambda p:(-p[col],p['harga']))[0]
        ch[cat]=pick; remain-=pick['harga']; remW-=w[cat]
    g=0
    while totals(ch)[0]>budget and g<25:
        g+=1; best=None; br=1e9
        for cat,p in ch.items():
            for q in parts[cat]:
                if q['harga']>=p['harga']: continue
                if cat=='GPU' and q['kapasitas']<min(p['kapasitas'],mv): continue
                if cat=='RAM' and q['kapasitas']<min(p['kapasitas'],mr): continue
                if cat=='Storage' and q['kapasitas']<min(p['kapasitas'],ms): continue
                if not bok(cat,q,brand): continue
                t=dict(ch); t[cat]=q
                if check(t,col): continue
                r=max(p[col]-q[col],.1)/(p['harga']-q['harga'])
                if r<br: br=r; best=(cat,q)
        if not best: break
        ch[best[0]]=best[1]
    return ch,col
def show(title,*a):
    ch,col=build(*a); t,w=totals(ch)
    print(f"\n## {title}  budget {a[1]/1e6:.1f}jt -> total {t/1e6:.2f}jt, ~{w}W {'OVER' if t>a[1] else ''} cek={check(ch,col)}")
    print('  '+' | '.join(f"{c[:3]}:{p['nama_part'].split(' (')[0][:26]}" for c,p in ch.items()))
ALL=CATS
show('Kreator 10-18 performa bebas','C',18e6,'performa','bebas',ALL,['Premiere','After'])
show('Gaming 5-10 hemat bebas','G',10e6,'hemat','bebas',ALL,['Game'])
show('Gaming 18-28 performa intel+nv','G',28e6,'performa','intel-nvidia',ALL,['Game'])
show('Gaming 18-28 performa AMD','G',28e6,'performa','amd',ALL,['Game'])
show('3D/AI >28 upgrade bebas','D',40e6,'upgrade','bebas',ALL,['Blender'])
show('Kantor <5jt hemat','O',5e6,'hemat','bebas',ALL,[])
show('Kantor 5-10 efisien','O',10e6,'efisien','bebas',ALL,['VS Code'])
show('Gaming <5jt','G',5e6,'hemat','bebas',ALL,['Game'])
show('Creator 12,5jt exact efisien full amd','C',12.5e6,'efisien','amd',ALL,['Premiere'])
show('Upgrade GPU saja gaming 8jt','G',8e6,'performa','bebas',['GPU'],[])
