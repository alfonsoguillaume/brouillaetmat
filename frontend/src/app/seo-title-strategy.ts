import {inject, Injectable} from '@angular/core';
import {DOCUMENT} from '@angular/common';
import {Meta, Title} from '@angular/platform-browser';
import {RouterStateSnapshot, TitleStrategy} from '@angular/router';

const ADRESSE_SITE = 'https://brouillaetmat.fr';
const TITRE_PAR_DEFAUT = 'Brouilla&Mat, club d\'échecs à Brouilla';
const DESCRIPTION_PAR_DEFAUT = 'Brouilla&Mat est un club d\'échecs affilié à la Fédération Française des Échecs, ouvert à tous dès 7 ans.';

// titre, description et indexation de chaque page, lus dans les routes
@Injectable({providedIn: 'root'})
export class SeoTitleStrategy extends TitleStrategy {
  private readonly titre = inject(Title);
  private readonly meta = inject(Meta);
  private readonly document = inject(DOCUMENT);

  override updateTitle(etat: RouterStateSnapshot): void {
    const titrePage = this.buildTitle(etat);
    const titreComplet = titrePage ? `${titrePage} | Brouilla&Mat` : TITRE_PAR_DEFAUT;
    this.titre.setTitle(titreComplet);

    // données de la page affichée (la plus profonde)
    let route = etat.root;
    while (route.firstChild) {
      route = route.firstChild;
    }
    const description = route.data['description'] ?? DESCRIPTION_PAR_DEFAUT;
    const robots = route.data['robots'] ?? 'index, follow';
    const pagePrivee = robots.startsWith('noindex');
    // pages privées : on ne publie jamais leur adresse (jeton dans l'adresse)
    const url = pagePrivee ? ADRESSE_SITE : ADRESSE_SITE + etat.url.split('?')[0];

    this.meta.updateTag({name: 'description', content: description});
    this.meta.updateTag({name: 'robots', content: robots});
    this.meta.updateTag({property: 'og:title', content: titreComplet});
    this.meta.updateTag({property: 'og:description', content: description});
    this.meta.updateTag({property: 'og:url', content: url});
    this.majAdresseCanonique(url, pagePrivee);
  }

  // adresse "officielle" de la page, évite les doublons pour Google
  private majAdresseCanonique(url: string, pagePrivee: boolean): void {
    let lien = this.document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (pagePrivee) {
      lien?.remove();
      return;
    }
    if (!lien) {
      lien = this.document.createElement('link');
      lien.setAttribute('rel', 'canonical');
      this.document.head.appendChild(lien);
    }
    lien.setAttribute('href', url);
  }
}
