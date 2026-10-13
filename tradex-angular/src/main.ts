import { bootstrapApplication } from '@angular/platform-browser';
import { provideRouter, withInMemoryScrolling } from '@angular/router';
import { AppComponent } from './app/app';
import { HomeComponent } from './app/home';
import { PageComponent } from './app/page';
import { provideZonelessChangeDetection } from '@angular/core';
bootstrapApplication(AppComponent, {providers:[provideZonelessChangeDetection(),provideRouter([{path:'',component:HomeComponent},{path:':slug',component:PageComponent},{path:'**',redirectTo:''}],withInMemoryScrolling({scrollPositionRestoration:'enabled',anchorScrolling:'enabled'}))]}).catch(console.error);
