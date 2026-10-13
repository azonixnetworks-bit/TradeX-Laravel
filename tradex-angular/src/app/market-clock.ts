import {ChangeDetectionStrategy, Component, OnDestroy, OnInit, computed, signal} from '@angular/core';

type Point = {x:number; y:number};
type Session = {name:string; timezone:string; open:number; close:number};

@Component({
  selector:'market-clock',
  standalone:true,
  changeDetection:ChangeDetectionStrategy.OnPush,
  template:`
    <section class="public-market-clock section" aria-labelledby="publicMarketClockTitle">
      <div class="container">
        <div class="market-clock-heading">
          <div>
            <p class="eyebrow">24 HOUR VISUAL CLOCK</p>
            <h2 id="publicMarketClockTitle">Global market sessions.<br><span class="gradient-text">One live view.</span></h2>
          </div>
          <div class="market-clock-live" aria-live="polite">
            <span></span>
            <div><strong>{{displayTime()}}</strong><small>{{displayDate()}} · Asia/Colombo</small></div>
          </div>
        </div>

        <div class="public-market-dial-card">
          <div class="dial-status-line">
            <span>FOREX TRADING CENTRES</span>
            <strong [class.is-open]="activeSessions().length">{{activeLabel()}}</strong>
          </div>
          <div class="public-market-dial-wrap">
            <svg class="public-market-dial" viewBox="0 0 700 700" role="img" aria-label="Live 24-hour global forex market session clock">
              <defs>
                <filter id="publicClockGlow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                <radialGradient id="publicClockFace"><stop offset="0" stop-color="#111914"/><stop offset=".58" stop-color="#0d1211"/><stop offset="1" stop-color="#080b0d"/></radialGradient>
              </defs>
              <circle cx="350" cy="350" r="323" fill="#080c10" stroke="rgba(212,175,55,.24)" stroke-width="2"/>
              <circle cx="350" cy="350" r="294" fill="url(#publicClockFace)" stroke="rgba(255,255,255,.07)"/>

              @for(tick of quarterTicks;track tick.minute){<line [attr.x1]="tick.inner.x" [attr.y1]="tick.inner.y" [attr.x2]="tick.outer.x" [attr.y2]="tick.outer.y" stroke="rgba(245,215,123,.14)" stroke-width=".8"/>}
              @for(hour of hourTicks;track hour.hour){
                <line [attr.x1]="hour.inner.x" [attr.y1]="hour.inner.y" [attr.x2]="hour.outer.x" [attr.y2]="hour.outer.y" [attr.stroke]="hour.major ? '#d4af37' : 'rgba(255,255,255,.22)'" [attr.stroke-width]="hour.major ? 2.2 : 1"/>
                <text [attr.x]="hour.label.x" [attr.y]="hour.label.y + 5" text-anchor="middle" [attr.fill]="hour.major ? '#f5d77b' : '#d5d7d4'" [attr.font-size]="hour.major ? 17 : 13" [attr.font-weight]="hour.major ? 850 : 650">{{hour.hour === 0 ? '24' : hour.hour.toString().padStart(2,'0')}}</text>
              }

              @for(line of guideLines;track line.minute){<line x1="350" y1="350" [attr.x2]="line.point.x" [attr.y2]="line.point.y" stroke="rgba(212,175,55,.08)"/>}
              @for(session of sessionRings();track session.name){
                <circle cx="350" cy="350" [attr.r]="session.radius" fill="none" stroke="rgba(154,158,151,.10)" stroke-width="9"/>
                <path [attr.d]="session.path" fill="none" [attr.stroke]="session.active ? '#f5d77b' : '#b9922c'" [attr.stroke-width]="session.active ? 13 : 9" stroke-linecap="round" [attr.opacity]="session.active ? 1 : .82" [attr.filter]="session.active ? 'url(#publicClockGlow)' : null"/>
                <text [attr.x]="session.label.x" [attr.y]="session.label.y + 3" text-anchor="middle" fill="#080b0d" font-size="9" font-weight="900" letter-spacing=".45" [attr.transform]="session.labelTransform">{{session.name.toUpperCase()}}</text>
                <circle [attr.cx]="session.close.x" [attr.cy]="session.close.y" [attr.r]="session.active ? 4.3 : 3.2" [attr.fill]="session.active ? '#49d39e' : '#c9b775'" stroke="#0a0d0c" stroke-width="1.5"/>
              }

              <circle cx="350" cy="350" r="91" fill="rgba(212,175,55,.02)" stroke="rgba(212,175,55,.15)"/>
              <circle cx="350" cy="350" r="70" fill="none" stroke="rgba(212,175,55,.10)"/>
              <path [attr.d]="currentWedge()" fill="rgba(73,211,158,.07)"/>
              <line x1="350" y1="350" x2="350" y2="66" stroke="#49d39e" stroke-width="2.2" stroke-linecap="round" [attr.transform]="pointerTransform()"/>
              <line x1="350" y1="350" x2="350" y2="225" stroke="#f2eee1" stroke-width="8" stroke-linecap="round" [attr.transform]="hourHandTransform()"/>
              <line x1="350" y1="350" x2="350" y2="182" stroke="#c5b98f" stroke-width="5" stroke-linecap="round" [attr.transform]="minuteHandTransform()"/>
              <line x1="350" y1="350" x2="350" y2="157" stroke="#d4af37" stroke-width="2" stroke-linecap="round" [attr.transform]="secondHandTransform()"/>
              <circle cx="350" cy="350" r="14" fill="#101612" stroke="#d4af37" stroke-width="2.2"/>
              <circle cx="350" cy="350" r="4" fill="#f5f5f5"/>
            </svg>
          </div>
          <div class="public-clock-legend" aria-label="Market clock legend"><span><i class="session-key"></i> Session window</span><span><i class="active-key"></i> Active now</span><span><i class="time-key"></i> Current time</span></div>
        </div>
      </div>
    </section>
  `
})
export class MarketClockComponent implements OnInit, OnDestroy {
  private readonly timezone='Asia/Colombo';
  private timer?:number;
  readonly now=signal(new Date());
  readonly sessions:Session[]=[
    {name:'Sydney',timezone:'Australia/Sydney',open:480,close:1020},
    {name:'Tokyo',timezone:'Asia/Tokyo',open:540,close:1080},
    {name:'Hong Kong',timezone:'Asia/Hong_Kong',open:540,close:1020},
    {name:'Singapore',timezone:'Asia/Singapore',open:540,close:1020},
    {name:'Dubai',timezone:'Asia/Dubai',open:540,close:1080},
    {name:'Frankfurt',timezone:'Europe/Berlin',open:480,close:1020},
    {name:'London',timezone:'Europe/London',open:480,close:1020},
    {name:'New York',timezone:'America/New_York',open:480,close:1020},
    {name:'Toronto',timezone:'America/Toronto',open:480,close:1020},
    {name:'Chicago',timezone:'America/Chicago',open:480,close:1020},
    {name:'Sao Paulo',timezone:'America/Sao_Paulo',open:540,close:1080}
  ];
  readonly hourTicks=Array.from({length:24},(_,hour)=>({hour,major:hour%6===0,inner:this.polar(hour%6===0?286:294,hour*60),outer:this.polar(317,hour*60),label:this.polar(304,hour*60)}));
  readonly quarterTicks=Array.from({length:96},(_,index)=>index*15).filter(minute=>minute%60!==0).map(minute=>({minute,inner:this.polar(310,minute),outer:this.polar(317,minute)}));
  readonly guideLines=[0,360,720,1080].map(minute=>({minute,point:this.polar(285,minute)}));

  readonly displayParts=computed(()=>this.parts(this.now(),this.timezone));
  readonly minuteOfDay=computed(()=>Number(this.displayParts().hour)*60+Number(this.displayParts().minute)+Number(this.displayParts().second)/60);
  readonly displayTime=computed(()=>`${this.displayParts().hour}:${this.displayParts().minute}:${this.displayParts().second}`);
  readonly displayDate=computed(()=>`${this.displayParts().weekday}, ${this.displayParts().day} ${this.displayParts().month} ${this.displayParts().year}`);
  readonly sessionRings=computed(()=>{
    const date=this.now();
    const displayOffset=this.offset(date,this.timezone);
    return this.sessions.map((session,index)=>{
      const start=this.mod(session.open-this.offset(date,session.timezone)+displayOffset,1440);
      const span=session.close-session.open;
      const end=start+span;
      const midpoint=this.mod(start+span/2,1440);
      const radius=270-index*14.2;
      const label=this.polar(radius,midpoint);
      let rotation=midpoint/1440*360;
      if(rotation>90&&rotation<270)rotation+=180;
      return {name:session.name,radius,path:this.arcPath(radius,start,end),label,labelTransform:`rotate(${rotation} ${label.x} ${label.y})`,close:this.polar(radius,this.mod(end,1440)),active:this.isActive(date,session)};
    });
  });
  readonly activeSessions=computed(()=>this.sessionRings().filter(session=>session.active));
  readonly activeLabel=computed(()=>this.activeSessions().length?`${this.activeSessions().length} ACTIVE`:'QUIET');
  readonly pointerTransform=computed(()=>`rotate(${this.minuteOfDay()/1440*360} 350 350)`);
  readonly hourHandTransform=computed(()=>`rotate(${(Number(this.displayParts().hour)%12)*30+Number(this.displayParts().minute)/2} 350 350)`);
  readonly minuteHandTransform=computed(()=>`rotate(${Number(this.displayParts().minute)*6+Number(this.displayParts().second)/10} 350 350)`);
  readonly secondHandTransform=computed(()=>`rotate(${Number(this.displayParts().second)*6} 350 350)`);
  readonly currentWedge=computed(()=>{
    const minute=this.minuteOfDay();
    const start=this.polar(250,minute-18);
    const end=this.polar(250,minute+18);
    return `M 350 350 L ${start.x} ${start.y} A 250 250 0 0 1 ${end.x} ${end.y} Z`;
  });

  ngOnInit(){this.timer=window.setInterval(()=>this.now.set(new Date()),1000);}
  ngOnDestroy(){if(this.timer!==undefined)window.clearInterval(this.timer);}
  private polar(radius:number,minute:number):Point{const angle=(minute/1440*360-90)*Math.PI/180;return{x:350+radius*Math.cos(angle),y:350+radius*Math.sin(angle)};}
  private arcPath(radius:number,startMinute:number,endMinute:number){const span=Math.max(.01,endMinute-startMinute);const start=this.polar(radius,startMinute);const end=this.polar(radius,this.mod(endMinute,1440));return `M ${start.x.toFixed(3)} ${start.y.toFixed(3)} A ${radius} ${radius} 0 ${span>720?1:0} 1 ${end.x.toFixed(3)} ${end.y.toFixed(3)}`;}
  private mod(value:number,divisor:number){return((value%divisor)+divisor)%divisor;}
  private parts(date:Date,timezone:string){const values:Record<string,string>={};new Intl.DateTimeFormat('en-GB',{timeZone:timezone,hourCycle:'h23',hour:'2-digit',minute:'2-digit',second:'2-digit',weekday:'short',day:'2-digit',month:'short',year:'numeric'}).formatToParts(date).forEach(part=>{if(part.type!=='literal')values[part.type]=part.value;});return values;}
  private offset(date:Date,timezone:string){const label=new Intl.DateTimeFormat('en-US',{timeZone:timezone,timeZoneName:'longOffset'}).formatToParts(date).find(part=>part.type==='timeZoneName')?.value??'GMT';const match=label.match(/GMT([+-])(\d{2}):?(\d{2})?/);if(!match)return 0;const minutes=Number(match[2])*60+Number(match[3]??0);return match[1]==='-'?-minutes:minutes;}
  private isActive(date:Date,session:Session){const values=this.parts(date,session.timezone);const weekday=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].indexOf(values['weekday']);const minute=Number(values['hour'])*60+Number(values['minute']);return weekday>=1&&weekday<=5&&minute>=session.open&&minute<session.close;}
}
