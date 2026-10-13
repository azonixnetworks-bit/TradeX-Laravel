import {AfterViewInit, Component, ElementRef, Input, OnChanges, OnDestroy, SimpleChanges, ViewChild, signal} from '@angular/core';

@Component({selector:'market-chart', standalone:true, template:`
<section class="chart-card" aria-label="TradingView market chart">
  <div class="chart-top"><span class="chart-mark" aria-hidden="true">X</span><strong>Market intelligence</strong><span class="provider-badge">TradingView</span></div>
  <div class="chart-toolbar"><div class="chart-tabs" aria-label="Select market">@for(market of markets;track market.name){<button [class.selected]="selected().name===market.name" [attr.aria-pressed]="selected().name===market.name" (click)="choose(market)">{{market.name}}</button>}</div><div class="intervals" aria-label="Chart timeframe">@for(timeframe of intervals;track timeframe.value){<button [class.chosen]="interval()===timeframe.value" [attr.aria-pressed]="interval()===timeframe.value" (click)="changeInterval(timeframe.value)">{{timeframe.label}}</button>}</div></div>
  @if(selected().name==='Stocks'){<div class="stock-regions" aria-label="Select stock market region">@for(market of stockMarkets;track market.region){<button [class.selected]="selected().ticker===market.ticker" [attr.aria-pressed]="selected().ticker===market.ticker" (click)="chooseStock(market)">{{market.region}}</button>}</div>}
  <div class="chart-stage">
    <div #widget class="tradingview-widget-container tv-container"></div>
    @if(status()!=='ready'){<div class="chart-loading" [class.chart-unavailable]="status()==='error'" role="status" aria-live="polite">@if(status()==='loading'){<span class="loading-ring" aria-hidden="true"></span><strong>Loading {{selected().label}}</strong><span>Connecting to TradingView</span>}@else{<strong>Chart is taking longer to load.</strong><span>Check your connection or open TradingView directly.</span><button class="retry-chart" (click)="loadChart()">Retry chart</button>}</div>}
  </div>
  <div class="chart-footer"><a [href]="chartUrl()" target="_blank" rel="noopener noreferrer">{{selected().label}} chart by TradingView</a><a [href]="chartUrl()" target="_blank" rel="noopener noreferrer" aria-label="Open chart on TradingView in a new tab">Open chart <span aria-hidden="true">↗</span></a></div>
  <p class="widget-note">Market data availability and delays depend on the instrument.</p>
</section>`})
export class ChartComponent implements AfterViewInit, OnChanges, OnDestroy {
  @ViewChild('widget',{static:true}) widget!:ElementRef<HTMLDivElement>;
  @Input() initialMarket: 'Forex'|'Crypto'|'Stocks' = 'Forex';
  @Input() compact = false;
  readonly markets=[{name:'Forex' as const,label:'EURUSD · IC Markets',ticker:'ICMARKETS:EURUSD'},{name:'Crypto' as const,label:'BTCUSD',ticker:'BITSTAMP:BTCUSD'},{name:'Stocks' as const,label:'US TEC 100 · IC Markets',ticker:'ICMARKETS:USTEC'}];
  readonly stockMarkets=[{name:'Stocks' as const,region:'CSE - LK',label:'CSE All Share · Sri Lanka',ticker:'CSELK:ASI'},{name:'Stocks' as const,region:'India',label:'India 50 · City Index',ticker:'CITYINDEX:INDIA50CFD'},{name:'Stocks' as const,region:'International',label:'US TEC 100 · IC Markets',ticker:'ICMARKETS:USTEC'}];
  readonly intervals=[{label:'15m',value:'15'},{label:'1h',value:'60'},{label:'4h',value:'240'},{label:'1d',value:'D'}];
  selected=signal(this.markets[0]);interval=signal('60');status=signal<'loading'|'ready'|'error'>('loading');
  private observer?:MutationObserver;private timeout?:ReturnType<typeof setTimeout>;private revision=0;private stopListening?:()=>void;
  chartUrl(){return 'https://www.tradingview.com/chart/?symbol='+encodeURIComponent(this.selected().ticker);}
  ngAfterViewInit(){this.applyInitialMarket();this.loadChart();}
  ngOnChanges(changes:SimpleChanges){if(changes['initialMarket']&&!changes['initialMarket'].firstChange&&this.widget){this.applyInitialMarket();this.loadChart();}}
  private applyInitialMarket(){this.selected.set(this.markets.find(market=>market.name===this.initialMarket)??this.markets[0]);}
  choose(market:typeof this.markets[number]){if(market===this.selected())return;this.selected.set(market);this.loadChart();}
  chooseStock(market:typeof this.stockMarkets[number]){if(market.ticker===this.selected().ticker)return;this.selected.set(market);this.loadChart();}
  changeInterval(value:string){if(value===this.interval())return;this.interval.set(value);this.loadChart();}
  private cleanup(){this.observer?.disconnect();this.stopListening?.();this.stopListening=undefined;if(this.timeout)clearTimeout(this.timeout);}
  loadChart(){
    this.cleanup();const revision=++this.revision;this.status.set('loading');
    const container=this.widget.nativeElement;container.replaceChildren();
    const host=document.createElement('div');host.className='tradingview-widget-container__widget';host.style.height='100%';host.style.width='100%';container.appendChild(host);
    const watchFrame=()=>{const frame=container.querySelector('iframe');if(!frame)return;frame.title=this.selected().label+' TradingView interactive chart';const ready=()=>{if(revision!==this.revision)return;this.status.set('ready');if(this.timeout)clearTimeout(this.timeout);};frame.addEventListener('load',ready,{once:true});this.stopListening=()=>frame.removeEventListener('load',ready);this.observer?.disconnect();};
    this.observer=new MutationObserver(watchFrame);this.observer.observe(container,{childList:true,subtree:true});
    const script=document.createElement('script');script.type='text/javascript';script.src='https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';script.async=true;
    script.textContent=JSON.stringify({autosize:true,symbol:this.selected().ticker,interval:this.interval(),timezone:'Asia/Colombo',theme:'dark',style:'1',locale:'en',backgroundColor:'#0b0d10',gridColor:'rgba(212,175,55,0.07)',allow_symbol_change:false,hide_top_toolbar:true,hide_side_toolbar:true,hide_legend:false,hide_volume:true,withdateranges:true,save_image:false,calendar:false,details:false,hotlist:false,studies:[],support_host:'https://www.tradingview.com'});
    script.onerror=()=>{if(revision===this.revision)this.status.set('error');};container.appendChild(script);watchFrame();
    this.timeout=setTimeout(()=>{if(revision===this.revision&&this.status()==='loading')this.status.set('error');},20000);
  }
  ngOnDestroy(){this.revision++;this.cleanup();this.widget?.nativeElement.replaceChildren();}
}
