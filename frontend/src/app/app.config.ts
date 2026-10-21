import {ApplicationConfig, provideBrowserGlobalErrorListeners} from '@angular/core';
import {provideRouter, TitleStrategy} from '@angular/router';
import {provideHttpClient, withInterceptors} from '@angular/common/http';
import {routes} from './app.routes';
import {authInterceptor} from './interceptors/auth.interceptor';
import {SeoTitleStrategy} from './seo-title-strategy';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    {provide: TitleStrategy, useClass: SeoTitleStrategy},
    provideHttpClient(withInterceptors([authInterceptor]))
  ]
};
