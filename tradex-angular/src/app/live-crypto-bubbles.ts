import {AfterViewInit, Component, ElementRef, OnDestroy, ViewChild, signal} from '@angular/core';

interface BinanceTicker { symbol:string; lastPrice:string; quoteVolume:string; priceChangePercent:string; }
interface BubbleNode { symbol:string; base:string; change:number; radius:number; targetRadius:number; x:number; y:number; vx:number; vy:number; seed:number; heading:number; cruise:number; phase:number; }

@Component({
  selector:'live-crypto-bubbles',
  standalone:true,
  template:`<div class="crypto-bubble-map live-bubble-preview" aria-label="Live Binance crypto market bubbles"><div class="bubble-map-top"><span>CRYPTO · BINANCE · 24H</span><span class="bubble-live-status" [class.error]="state()==='unavailable'">{{status()}}</span><span><i class="legend-dot gain"></i>Gain <i class="legend-dot loss"></i>Loss</span></div><div class="bubble-canvas-wrap"><canvas #canvas aria-label="Animated live crypto market bubble map"></canvas>@if(state()!=='live'){<span class="bubble-canvas-state" [class.error]="state()==='unavailable'">{{state()==='loading'?'Loading live market data…':'Live market data is temporarily unavailable.'}}</span>}</div></div>`
})
export class LiveCryptoBubblesComponent implements AfterViewInit, OnDestroy {
  @ViewChild('canvas',{static:true}) canvasRef!:ElementRef<HTMLCanvasElement>;
  state=signal<'loading'|'live'|'unavailable'>('loading');
  private canvas!:HTMLCanvasElement;
  private context!:CanvasRenderingContext2D;
  private nodes:BubbleNode[]=[];
  private width=0;
  private height=0;
  private frame=0;
  private previous=0;
  private socket?:WebSocket;
  private abort?:AbortController;
  private retry?:ReturnType<typeof setTimeout>;
  private resizeObserver?:ResizeObserver;
  private destroyed=false;

  ngAfterViewInit(){
    this.canvas=this.canvasRef.nativeElement;
    const context=this.canvas.getContext('2d');
    if(!context){this.state.set('unavailable');return;}
    this.context=context;
    this.resizeObserver=new ResizeObserver(()=>this.resize());
    this.resizeObserver.observe(this.canvas.parentElement!);
    this.resize();
    void this.load();
  }

  status(){return this.state()==='loading'?'CONNECTING':this.state()==='unavailable'?'UNAVAILABLE':'LIVE';}

  private async load(){
    this.abort=new AbortController();
    try{
      const response=await fetch('https://data-api.binance.vision/api/v3/ticker/24hr',{signal:this.abort.signal});
      if(!response.ok) throw new Error('Snapshot unavailable');
      const tickers=await response.json() as BinanceTicker[];
      const stablecoins=new Set(['USDT','USDC','USDS','USDE','USDF','USDD','FDUSD','USD1','RLUSD','TUSD','DAI','BUSD','PYUSD','USDP']);
      const selected=tickers.filter(t=>{const base=t.symbol.replace(/USDT$/,'');return t.symbol.endsWith('USDT')&&!stablecoins.has(base)&&Number(t.lastPrice)>0&&Number(t.quoteVolume)>0;})
        .sort((a,b)=>Number(b.quoteVolume)-Number(a.quoteVolume)).slice(0,20);
      if(selected.length<1) throw new Error('No usable markets');
      this.nodes=[];
      selected.forEach(ticker=>this.nodes.push(this.createNode(ticker)));
      for(let pass=0;pass<56;pass+=1)this.separateCollisions();
      this.state.set('live');
      this.connect();
      this.frame=requestAnimationFrame(time=>this.animate(time));
    }catch(error){if((error as Error).name!=='AbortError') this.state.set('unavailable');}
  }

  private createNode(ticker:BinanceTicker):BubbleNode{
    const seed=this.hash(ticker.symbol);
    const radius=this.radiusFor(Number(ticker.priceChangePercent));
    const point=this.findSpawnPoint(radius,seed);
    return {symbol:ticker.symbol,base:ticker.symbol.replace(/USDT$/,''),change:Number(ticker.priceChangePercent)||0,radius,targetRadius:radius,x:point.x,y:point.y,vx:(this.unit(seed+3)-.5)*1.45,vy:(this.unit(seed+9)-.5)*1.45,seed,heading:this.unit(seed+211)*Math.PI*2,cruise:.88+(this.unit(seed+307)*.36),phase:this.unit(seed+401)*Math.PI*2};
  }

  private connect(){
    if(this.destroyed||this.socket||!this.nodes.length) return;
    const streams=this.nodes.map(node=>`${node.symbol.toLowerCase()}@ticker`).join('/');
    try{
      this.socket=new WebSocket(`wss://stream.binance.com:9443/stream?streams=${streams}`);
      this.socket.onmessage=event=>{try{const data=(JSON.parse(event.data).data??{}) as {s?:string;P?:string};const node=this.nodes.find(item=>item.symbol===(data.s??'').toUpperCase());const change=Number(data.P);if(node&&Number.isFinite(change)){node.change=change;node.targetRadius=this.radiusFor(change);}}catch{}};
      this.socket.onclose=()=>{this.socket=undefined;if(!this.destroyed)this.retry=setTimeout(()=>this.connect(),3000);};
    }catch{/* Snapshot remains visible and retry is handled by the next navigation. */}
  }

  private resize(){
    const box=this.canvas.parentElement!.getBoundingClientRect();
    this.width=Math.max(1,box.width);this.height=Math.max(280,box.height);
    const ratio=Math.min(2,window.devicePixelRatio||1);
    this.canvas.width=Math.round(this.width*ratio);this.canvas.height=Math.round(this.height*ratio);
    this.context.setTransform(ratio,0,0,ratio,0,0);
    this.nodes.forEach(node=>this.bound(node));
  }

  private animate(timestamp:number){
    if(this.destroyed)return;
    const dt=Math.min(2,Math.max(.4,(timestamp-this.previous||16.67)/16.67));this.previous=timestamp;
    this.nodes.forEach(node=>{node.radius+=(node.targetRadius-node.radius)*.12*dt;node.heading+=(Math.sin((timestamp*.0007)+node.phase)*.018+Math.cos((timestamp*.00037)+(node.phase*2))*.009)*dt;const desiredX=Math.cos(node.heading)*node.cruise,desiredY=Math.sin(node.heading)*node.cruise;node.vx+=(desiredX-node.vx)*.034*dt;node.vy+=(desiredY-node.vy)*.034*dt;const speed=Math.hypot(node.vx,node.vy);if(speed>1.42){node.vx=node.vx/speed*1.42;node.vy=node.vy/speed*1.42;}node.x+=node.vx*dt;node.y+=node.vy*dt;this.bound(node);});
    for(let pass=0;pass<30;pass+=1)this.separateCollisions();
    this.draw();this.frame=requestAnimationFrame(time=>this.animate(time));
  }

  private separateCollisions(){for(let a=0;a<this.nodes.length;a+=1){for(let b=a+1;b<this.nodes.length;b+=1){const left=this.nodes[a],right=this.nodes[b],dx=right.x-left.x,dy=right.y-left.y,distance=Math.hypot(dx,dy)||.01,min=left.radius+right.radius+17;if(distance>=min)continue;const nx=dx/distance,ny=dy/distance,push=(min-distance)*.62;left.x-=nx*push;left.y-=ny*push;right.x+=nx*push;right.y+=ny*push;left.vx-=nx*.24;left.vy-=ny*.24;right.vx+=nx*.24;right.vy+=ny*.24;this.bound(left);this.bound(right);}}}

  private bound(node:BubbleNode){const margin=node.radius+3;if(node.x<margin){node.x=margin;node.vx=Math.abs(node.vx);}if(node.x>this.width-margin){node.x=this.width-margin;node.vx=-Math.abs(node.vx);}if(node.y<margin){node.y=margin;node.vy=Math.abs(node.vy);}if(node.y>this.height-margin){node.y=this.height-margin;node.vy=-Math.abs(node.vy);}}
  private findSpawnPoint(radius:number,seed:number){const edge=radius+12,usableWidth=Math.max(1,this.width-(edge*2)),usableHeight=Math.max(1,this.height-(edge*2));for(let attempt=0;attempt<480;attempt+=1){const x=edge+(this.unit(seed+(attempt*17)+11)*usableWidth),y=edge+(this.unit(seed+(attempt*29)+97)*usableHeight),clear=this.nodes.every(node=>Math.hypot(node.x-x,node.y-y)>=node.radius+radius+20);if(clear)return {x,y};}return {x:this.width/2,y:this.height/2};}
  private radiusFor(change:number){const min=this.width<500?16:18,max=this.width<500?36:49;return min+(max-min)*Math.sqrt(Math.min(20,Math.abs(change||0))/20);}
  private hash(value:string){return [...value].reduce((hash,char)=>Math.imul(hash^char.charCodeAt(0),16777619),2166136261)>>>0;}
  private unit(seed:number){const value=Math.sin(seed*12.9898)*43758.5453;return value-Math.floor(value);}
  private draw(){const ctx=this.context;ctx.clearRect(0,0,this.width,this.height);this.nodes.forEach(node=>this.drawNode(ctx,node));}
  private drawNode(ctx:CanvasRenderingContext2D,node:BubbleNode){const positive=node.change>=0,r=node.radius,highlight=positive?'rgba(224,255,242,.98)':'rgba(255,220,220,.98)',light=positive?'rgba(45,232,152,.96)':'rgba(255,94,98,.96)',mid=positive?'rgba(8,150,88,.98)':'rgba(180,38,48,.98)',dark=positive?'rgba(2,46,31,1)':'rgba(57,7,14,1)',rim=positive?'rgba(80,255,171,.92)':'rgba(255,107,107,.92)',glow=positive?'rgba(32,220,132,.46)':'rgba(244,72,72,.46)';ctx.save();ctx.beginPath();ctx.arc(node.x,node.y,r,0,Math.PI*2);ctx.shadowBlur=Math.min(30,r*.55);ctx.shadowColor=glow;const gradient=ctx.createRadialGradient(node.x-r*.34,node.y-r*.38,Math.max(1,r*.05),node.x,node.y,r*1.08);gradient.addColorStop(0,highlight);gradient.addColorStop(.2,light);gradient.addColorStop(.55,mid);gradient.addColorStop(1,dark);ctx.fillStyle=gradient;ctx.fill();ctx.shadowBlur=0;ctx.lineWidth=1.8;ctx.strokeStyle=rim;ctx.stroke();ctx.textAlign='center';ctx.textBaseline='middle';ctx.shadowBlur=3;ctx.shadowColor='rgba(0,0,0,.7)';ctx.fillStyle='#fff';ctx.font=`800 ${Math.max(9,Math.min(18,r*.29))}px system-ui`;ctx.fillText(node.base.slice(0,7),node.x,node.y-r*.12);ctx.fillStyle=positive?'#b8ffdc':'#ffd0d4';ctx.font=`800 ${Math.max(8,Math.min(14,r*.21))}px system-ui`;ctx.fillText(`${node.change>=0?'+':''}${node.change.toFixed(2)}%`,node.x,node.y+r*.20);ctx.restore();}

  ngOnDestroy(){this.destroyed=true;this.abort?.abort();clearTimeout(this.retry);cancelAnimationFrame(this.frame);this.resizeObserver?.disconnect();if(this.socket){this.socket.onclose=null;this.socket.close();}}
}
