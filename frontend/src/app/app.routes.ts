import {Routes} from '@angular/router';
import {LegalComponent} from './legal-component/legal-component';
import {PolitiqueComponent} from './politique-component/politique-component';
import {ConnexionComponent} from './connexion-component/connexion-component';
import {AccueilComponent} from './accueil-component/accueil-component';
import {ClubComponent} from './club-component/club-component';
import {BureauComponent} from './bureau-component/bureau-component';
import {InformationComponent} from './information-component/information-component';
import {JouerComponent} from './jouer-component/jouer-component';
import {TournoisComponent} from './tournois-component/tournois-component';
import {AdministrationComponent} from './administration-component/administration-component';
import {ProfilComponent} from './profil-component/profil-component';
import {InscriptionComponent} from './inscription-component/inscription-component';
import {OublieComponent} from './oublie-component/oublie-component';
import {ConfirmerEmailComponent} from './confirmer-email-component/confirmer-email-component';
import {
  ReinitialiserMotDePasseComponent
} from './reinitialiser-mot-de-passe-component/reinitialiser-mot-de-passe-component';
import {DocumentComponent} from './document-component/document-component';
import {BibliothequeComponent} from './bibliotheque-component/bibliotheque-component';
import {PageIntrouvableComponent} from './page-introuvable-component/page-introuvable-component';
import {adminGuard, gestionnaireGuard} from './guards/role.guard';

export const routes: Routes = [
  // Redirection de la racine vers l'accueil
  {path: '', redirectTo: 'accueil', pathMatch: 'full'},

  // Pages principales
  {path: 'accueil', component: AccueilComponent, title: 'Club d\'échecs à Brouilla', data: {description: "Brouilla&Mat est un club d'échecs affilié à la Fédération Française des Échecs, ouvert à tous dès 7 ans, quel que soit le niveau. Parties en ligne, classement et tournois."}},
  {path: 'club', component: ClubComponent, title: 'Actualités du club', data: {description: "Les actualités et les photos du club d'échecs Brouilla&Mat : tournois, rencontres et vie du club à Brouilla."}},
  {path: 'bureau', component: BureauComponent, title: 'Les membres du bureau', data: {description: "Les membres du bureau du club d'échecs Brouilla&Mat et comment les contacter."}},
  {path: 'information', component: InformationComponent, title: 'Informations pratiques', data: {description: "Lieu, horaires des cours (mercredi 17h-19h, vendredi 18h-20h) et affiliation FFE du club d'échecs Brouilla&Mat, à la mairie de Brouilla."}},
  {path: 'jouer', component: JouerComponent, title: 'Jouer', data: {robots: 'noindex, nofollow'}},
  {path: 'tournois', component: TournoisComponent, title: 'Tournois', data: {robots: 'noindex, nofollow'}},
  {path: 'administration', component: AdministrationComponent, canActivate: [gestionnaireGuard], title: 'Administration', data: {robots: 'noindex, nofollow'}},
  {path: 'profil', component: ProfilComponent, title: 'Mon profil', data: {robots: 'noindex, nofollow'}},
  {path: 'inscription', component: InscriptionComponent, title: 'Inscription', data: {description: "Créez votre compte sur le site du club d'échecs Brouilla&Mat. Réservé aux adhérents de l'association, le club valide chaque inscription."}},
  {path: 'connexion', component: ConnexionComponent, title: 'Connexion', data: {robots: 'noindex, follow'}},
  {path: 'oublie', component: OublieComponent, title: 'Mot de passe oublié', data: {robots: 'noindex, nofollow'}},
  {path: 'confirmer-email/:token', component: ConfirmerEmailComponent, title: 'Confirmation de l\'e-mail', data: {robots: 'noindex, nofollow'}},
  {path: 'reinitialiser-mot-de-passe/:token', component: ReinitialiserMotDePasseComponent, title: 'Nouveau mot de passe', data: {robots: 'noindex, nofollow'}},
  {path: 'document', component: DocumentComponent, canActivate: [adminGuard], title: 'Documents', data: {robots: 'noindex, nofollow'}},
  {path: 'bibliotheque', component: BibliothequeComponent, canActivate: [gestionnaireGuard], title: 'Bibliothèque', data: {robots: 'noindex, nofollow'}},

  // Footer
  {path: 'mentions-legales', component: LegalComponent, title: 'Mentions légales', data: {description: "Mentions légales du site du club d'échecs Brouilla&Mat : association, directeur de la publication et hébergeur."}},
  {path: 'politique-confidentialite', component: PolitiqueComponent, title: 'Politique de confidentialité', data: {description: "Politique de confidentialité du site du club d'échecs Brouilla&Mat : données collectées, durée de conservation et vos droits."}},

  // Page 404 : toute URL inconnue affiche ce composant
  {path: '**', component: PageIntrouvableComponent, title: 'Page introuvable', data: {robots: 'noindex, nofollow'}}
];
