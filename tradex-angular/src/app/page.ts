import {Component, OnDestroy, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {Title} from '@angular/platform-browser';
import {Subscription} from 'rxjs';
import {PAGES, PageData} from './pages-data';
import {ChartComponent} from './chart';
import {LiveCryptoBubblesComponent} from './live-crypto-bubbles';
import {PLATFORM_URL} from './config';

@Component({
  selector: 'app-page',
  standalone: true,
  imports: [RouterLink, ChartComponent, LiveCryptoBubblesComponent],
  template: `
    <section class="page-hero" [class.platform-page-hero]="slug()==='platform'" [class.education-page-hero]="slug()==='education'" [class.market-intelligence-page-hero]="slug()==='market-intelligence'" [class.trade-ideas-page-hero]="slug()==='trade-ideas'">
      <div class="container">
        <a class="breadcrumb" routerLink="/">Home</a><span class="breadcrumb-separator">/</span><span class="breadcrumb">{{page().eyebrow}}</span>
        <div class="page-title-row" [class.platform-title-row]="slug()==='platform'">
          <div>
            <p class="eyebrow">{{page().eyebrow}}</p>
            <h1>{{page().title}}</h1>
            <p class="lead">{{page().lead}}</p>
            @if(slug()==='platform'){
              <a class="button platform-hero-action" [href]="login">Explore the platform <span aria-hidden="true">↗</span></a>
            }
          </div>
          @if(slug()==='platform'){
            <div class="phone-stage" aria-label="TradeX mobile platform preview">
              <span class="phone-glow" aria-hidden="true"></span>
              <div class="phone-shell">
                <div class="phone-top"><span></span><i></i></div>
                <div class="phone-screen phone-dashboard-preview"><img src="app/tradex-dashboard.jpeg" alt="TradeX mobile dashboard preview" loading="eager"></div>
              </div>
            </div>
          } @else if(slug()==='education'){
            <div class="education-hero-visual" aria-label="All-market trading education workspace">
              <img src="app/education-all-markets-hero.png" alt="A premium trading education workspace connecting world markets" loading="eager">
              <span class="education-hero-badge">FOREX · CRYPTO · STOCKS · DERIV</span>
            </div>
          } @else {
            <div class="page-index" aria-hidden="true">{{slug()==='faq'?'?':slug()==='contact'?'✳':'X'}}</div>
          }
        </div>
      </div>
    </section>

    <section class="section page-content" [class.education-page-content]="slug()==='education'" [class.market-intelligence-page-content]="slug()==='market-intelligence'" [class.trade-ideas-page-content]="slug()==='trade-ideas'"><div class="container">
      @if(slug()==='faq'){
        <div class="faq-layout"><div><p class="eyebrow">A LITTLE MORE CLARITY</p><h2>Good questions.<br><span class="gradient-text">Clear answers.</span></h2><p class="lead">Need help with something else?</p><a class="button outline" routerLink="/contact">Contact TradeX</a></div><div class="faq-list">@for(f of faqs;track f.q;let i=$index){<details [open]="i===0"><summary>{{f.q}}<span>+</span></summary><p>{{f.a}}</p></details>}</div></div>
      } @else if(slug()==='contact'){
        <div class="contact-grid"><article class="info-card"><p class="eyebrow">01 / EXISTING CUSTOMERS</p><h2>Your workspace,<br>one sign-in away.</h2><p>Access your account information and available support tools through the authenticated platform.</p><a class="button" [href]="login">Sign in to TradeX <span>↗</span></a></article><article class="info-card"><p class="eyebrow">02 / PUBLIC ENQUIRIES</p><h2>Let's start a<br>conversation.</h2><p>Billionaire Trading Institute<br>Registration: PV00296953</p><a class="contact-phone" href="tel:+94722542311">+94 72 254 2311</a><p class="muted">For account-specific support, please sign in.</p></article></div>
      } @else {
        @if(slug()==='platform'){
          <div class="platform-capability-heading"><div><p class="eyebrow">EVERYTHING CONNECTED</p><h2>Seven capabilities.<br><span class="gradient-text">One focused workspace.</span></h2></div><p>Move from context to execution with a clear workflow for ideas, journal review, routines and market-specific signals.</p></div>
          <div class="platform-capability-grid">@for(item of platformCapabilities;track item.title;let i=$index){<article><span class="capability-number">0{{i+1}}</span><span class="capability-icon" aria-hidden="true">{{item.icon}}</span><h3>{{item.title}}</h3><p>{{item.description}}</p></article>}</div>
          <p class="platform-skill-quote">Bring your trading skills to a professional trade.</p>
        }
        @if(slug()==='education'){
          <section class="education-bubbles" aria-labelledby="education-bubbles-title">
            <div class="education-bubbles-copy"><p class="eyebrow">LIVE CRYPTO MARKET BUBBLES</p><h2 id="education-bubbles-title">Learn to read market<br><span class="gradient-text">strength and weakness.</span></h2><p>Train your eye to see the market as one connected landscape before narrowing into a specific idea or trade plan.</p><span class="bubble-disclaimer">Top 20 Binance USDT spot markets · live 24H change determines bubble colour and size</span></div>
            <live-crypto-bubbles/>
          </section>
        }
        <div class="content-blocks">@for(b of page().blocks;track b.title;let i=$index){<article><span class="block-number">0{{i+1}}</span><div><h2>{{b.title}}</h2><p>{{b.text}}</p></div></article>}</div>
        @if(chartMarket()){<div class="page-chart"><market-chart [initialMarket]="chartMarket()!"/></div>}
        @if(slug()==='deriv'){<div class="availability-note" role="note"><strong>Deriv chart availability</strong><p>TradingView's public widget does not provide the proprietary Deriv feed used by the authenticated TradeX screener. Sign in to access supported Deriv market data.</p></div>}
        @if(slug()==='market-intelligence'){<div class="feature-grid compact">@for(p of platformLinks;track p.path){<a class="feature-card" [routerLink]="p.path"><h3>{{p.title}}</h3><p>{{p.description}}</p><span class="card-link">Explore <span>↗</span></span></a>}</div>}
        @if(!['terms','privacy','risk-disclosure'].includes(slug())){<div class="page-callout"><div><p class="eyebrow">YOUR PROCESS, CONNECTED</p><h2>Bring your next decision into focus.</h2></div><a class="button" [href]="login">Access platform <span>↗</span></a></div>}
      }
    </div></section>
  `
})
export class PageComponent implements OnDestroy {
  slug=signal('');
  page=signal<PageData>(PAGES['about']);
  login=PLATFORM_URL;
  sub:Subscription;
  chartMarket=signal<'Forex'|'Crypto'|'Stocks'|null>(null);
  faqs=[
    {q:'What is TradeX OS?',a:'TradeX OS is a workspace that combines structured education, market intelligence and risk-aware planning tools.'},
    {q:'Does TradeX OS provide investment advice?',a:'No. TradeX OS provides education, information and tools. You remain responsible for your own decisions.'},
    {q:'Does the platform guarantee results?',a:'No. Financial markets involve risk and no platform, tool or educational resource can guarantee an outcome.'},
    {q:'Can I access the platform on mobile?',a:'The public website and platform views are designed to be responsive so you can access supported features on a modern mobile browser.'},
    {q:'How do I get help with my account?',a:'Use the authenticated platform support route when available. For public enquiries, contact Billionaire Trading Institute on +94 72 254 2311.'}
  ];
  platformLinks=[
    {path:'/education',title:'Education',description:'Structured paths for practical understanding.'},
    {path:'/market-intelligence',title:'Market intelligence',description:'Organise your attention across markets.'},
    {path:'/tools',title:'Planning tools',description:'Make risk visible before taking action.'}
  ];
  platformCapabilities=[
    {icon:'◈',title:'Second Brain Entry Ideas',description:'Capture structured entry ideas with market context, planned levels and a clear invalidation before execution.'},
    {icon:'↗',title:'Trade Ideas',description:'Organise long-term, short-term and intraday trade ideas in one focused research workflow.'},
    {icon:'▤',title:'Trade Journal',description:'Review each decision, outcome and lesson so your process improves with evidence instead of emotion.'},
    {icon:'◷',title:'Trading Routine Planner',description:'Build a repeatable routine for preparation, execution discipline and deliberate review.'},
    {icon:'ƒ',title:'Forex Trading Signals',description:'Keep Forex market signals and structured setup context visible when you need them.'},
    {icon:'₿',title:'Crypto Trading Signals',description:'Follow Crypto trading signals with the same clear context and process-led planning.'},
    {icon:'▥',title:'Stocks Trading Signals',description:'View Stocks signals across CSE Sri Lanka, India and international markets from one workspace.'}
  ];

  constructor(route:ActivatedRoute,title:Title){
    this.sub=route.paramMap.subscribe(params=>{
      const s=params.get('slug')??'';
      this.slug.set(s);
      this.chartMarket.set(s==='crypto'?'Crypto':s==='stocks'?'Stocks':['forex','market-intelligence'].includes(s)?'Forex':null);
      const page=PAGES[s]??(s==='faq'
        ?{title:'Questions, answered with clarity.',eyebrow:'Support centre',lead:'Helpful information about TradeX OS and the way the platform works.',blocks:[]}
        :s==='contact'
          ?{title:'Start with a clear conversation.',eyebrow:'Contact TradeX',lead:'Public enquiries or account support. Find the right way to reach us.',blocks:[]}
          :{title:'This page could not be found.',eyebrow:'Page not found',lead:'Use the navigation or return home to continue exploring TradeX.',blocks:[]});
      this.page.set(page);
      title.setTitle(page.eyebrow+' | TradeX OS');
    });
  }

  ngOnDestroy(){this.sub.unsubscribe();}
}
