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
import {DocumentComponent} from './document-component/document-component';
import {BibliothequeComponent} from './bibliotheque-component/bibliotheque-component';
import {adminGuard, gestionnaireGuard} from './guards/role.guard';

export const routes: Routes = [
  // Redirection de la racine vers l'accueil
  {path: '', redirectTo: 'accueil', pathMatch: 'full'},

  // Pages principales
  {path: 'accueil', component: AccueilComponent},
  {path: 'club', component: ClubComponent},
  {path: 'bureau', component: BureauComponent},
  {path: 'information', component: InformationComponent},
  {path: 'jouer', component: JouerComponent},
  {path: 'tournois', component: TournoisComponent},
  {path: 'administration', component: AdministrationComponent, canActivate: [gestionnaireGuard]},
  {path: 'profil', component: ProfilComponent},
  {path: 'inscription', component: InscriptionComponent},
  {path: 'connexion', component: ConnexionComponent},
  {path: 'oublie', component: OublieComponent},
  {path: 'document', component: DocumentComponent, canActivate: [adminGuard]},
  {path: 'bibliotheque', component: BibliothequeComponent, canActivate: [gestionnaireGuard]},

  // Footer
  {path: 'mentions-legales', component: LegalComponent},
  {path: 'politique-confidentialite', component: PolitiqueComponent},

  // Redirection globale
  {path: '**', redirectTo: 'accueil'}
];
