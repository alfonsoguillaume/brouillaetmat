import {inject} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {CanActivateFn, Router} from '@angular/router';
import {filter, map, take} from 'rxjs/operators';
import {AuthService} from '../services/auth.service';

/**
 * Garde générique : attend que le profil soit chargé (voir profilChargeSignal
 * dans AuthService), puis vérifie le rôle avec la fonction donnée. Si refusé,
 * redirige vers l'accueil au lieu d'afficher la page.
 */
function creerGardeDeRole(verifierRole: (auth: AuthService) => boolean): CanActivateFn {
  return () => {
    const authService = inject(AuthService);
    const router = inject(Router);

    return toObservable(authService.profilChargeSignal).pipe(
      filter((pret) => pret),
      take(1),
      map(() => verifierRole(authService) ? true : router.createUrlTree(['/accueil']))
    );
  };
}

export const adminGuard: CanActivateFn = creerGardeDeRole((auth) => auth.isAdmin());
export const gestionnaireGuard: CanActivateFn = creerGardeDeRole((auth) => auth.isGestionnaire());
