import {ChangeDetectorRef, Component, effect, inject} from '@angular/core';
import {DatePipe} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {AdminService, InscriptionEnAttente, Membre} from '../services/admin.service';
import {AuthService} from '../services/auth.service';
import {ArticleService} from '../services/article.service';
import {RouterLink} from '@angular/router';

@Component({
  imports: [DatePipe, ReactiveFormsModule, RouterLink],
  selector: 'app-administration-component',
  styleUrl: './administration-component.css',
  templateUrl: './administration-component.html',
})
export class AdministrationComponent {
  private adminService = inject(AdminService);
  private authService = inject(AuthService);
  private articleService = inject(ArticleService);
  private fb = inject(FormBuilder);
  private cdr = inject(ChangeDetectorRef);

  private listesDejaChargees = false;

  // --- Inscriptions en attente (déjà existant) ---
  inscriptions: InscriptionEnAttente[] = [];
  modaleOuverte = false;
  confirmationEnCours: { id: number; action: 'valider' | 'refuser' } | null = null;

  // --- Liste des membres ---
  membres: Membre[] = [];

  constructor() {
    // Contrairement à ngOnInit (qui ne s'exécute qu'une fois, avant que
    // AuthService ait fini de récupérer le rôle après un rafraîchissement),
    // effect() se relance automatiquement à chaque fois que la valeur lue
    // à l'intérieur change — ici, dès que isAdmin() passe de false à true.
    effect(() => {
      if (this.authService.isAdmin() && !this.listesDejaChargees) {
        this.listesDejaChargees = true;
        this.chargerInscriptions();
        this.chargerMembres();
      }
    });
  }

  // --- Modale de modification d'un membre ---
  membreSelectionne: Membre | null = null;
  messagesErreursMembre: string[] = [];
  messageSuccesMembre = '';

  membreForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
    prenom: ['', Validators.required],
    pseudo: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    telephone: [''],
    emailTuteur: [''],
  });

  // --- Changement de rôle ---
  roleChoisi = '';
  confirmationRoleEnCours = false;

  // --- Blocage / déblocage ---
  confirmationBlocageEnCours: { membre: Membre; action: 'bloquer' | 'debloquer' } | null = null;

  // --- Suppression ---
  confirmationSuppressionEnCours: Membre | null = null;
  messageErreurSuppression = '';

  // --- Modale d'écriture d'article ---
  modaleArticleOuverte = false;
  photoArticleSelectionnee: File | null = null;
  messagesErreursArticle: string[] = [];
  messageSuccesArticle = '';
  publicationEnCours = false;

  articleForm: FormGroup = this.fb.group({
    titre: ['', Validators.required],
    contenu: ['', Validators.required],
  });

  estAdmin(): boolean {
    return this.authService.isAdmin();
  }

  estGestionnaire(): boolean {
    return this.authService.isGestionnaire();
  }

  // ---------- Inscriptions en attente ----------

  chargerInscriptions(): void {
    this.adminService.listeInscriptions().subscribe({
      next: (data) => {
        this.inscriptions = data;
        this.cdr.detectChanges();
      }
    });
  }

  ouvrirModale(): void {
    this.modaleOuverte = true;
  }

  fermerModale(): void {
    this.modaleOuverte = false;
    this.confirmationEnCours = null;
  }

  demanderConfirmation(id: number, action: 'valider' | 'refuser'): void {
    this.confirmationEnCours = {id, action};
  }

  annulerConfirmation(): void {
    this.confirmationEnCours = null;
  }

  confirmer(): void {
    if (!this.confirmationEnCours) {
      return;
    }

    const {id, action} = this.confirmationEnCours;
    const requete = action === 'valider'
      ? this.adminService.valider(id)
      : this.adminService.refuser(id);

    requete.subscribe({
      next: () => {
        this.inscriptions = this.inscriptions.filter(inscription => inscription.id !== id);
        this.confirmationEnCours = null;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Liste des membres ----------

  chargerMembres(): void {
    this.adminService.listeMembres().subscribe({
      next: (data) => {
        this.membres = data;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Modale de modification d'un membre ----------

  ouvrirModaleMembre(membre: Membre): void {
    this.membreSelectionne = membre;
    this.roleChoisi = membre.role;
    this.messagesErreursMembre = [];
    this.messageSuccesMembre = '';

    this.membreForm.setValue({
      nom: membre.nom,
      prenom: membre.prenom,
      pseudo: membre.pseudo,
      email: membre.email,
      telephone: membre.telephone ?? '',
      emailTuteur: membre.email_tuteur ?? '',
    });
  }

  fermerModaleMembre(): void {
    this.membreSelectionne = null;
    this.confirmationRoleEnCours = false;
  }

  private construireMessagesErreursMembre(): string[] {
    const noms: { [cle: string]: string } = {
      nom: 'le nom',
      prenom: 'le prénom',
      pseudo: 'le pseudo',
      email: "l'e-mail",
    };
    const messages: string[] = [];

    for (const cle in noms) {
      const controle = this.membreForm.get(cle);
      if (controle?.errors?.['required']) {
        messages.push(`Remplissez ${noms[cle]}.`);
      } else if (controle?.errors?.['email']) {
        messages.push(`Écrivez ${noms[cle]} avec un @, par exemple : nom@exemple.fr.`);
      }
    }

    return messages;
  }

  enregistrerMembre(): void {
    if (!this.membreSelectionne) {
      return;
    }

    this.messagesErreursMembre = [];
    this.messageSuccesMembre = '';

    if (!this.membreForm.valid) {
      this.messagesErreursMembre = this.construireMessagesErreursMembre();
      this.cdr.detectChanges();
      return;
    }

    const valeurs = this.membreForm.value;

    this.adminService.modifierMembre(this.membreSelectionne.id, {
      nom: valeurs.nom,
      prenom: valeurs.prenom,
      pseudo: valeurs.pseudo,
      email: valeurs.email,
      telephone: valeurs.telephone || null,
      email_tuteur: valeurs.emailTuteur || null,
    }).subscribe({
      next: () => {
        this.messageSuccesMembre = 'Membre mis à jour.';
        this.chargerMembres();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messagesErreursMembre = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Changement de rôle ----------

  onRoleChange(valeur: string): void {
    this.roleChoisi = valeur;
  }

  demanderConfirmationRole(): void {
    if (!this.membreSelectionne || this.roleChoisi === this.membreSelectionne.role) {
      return;
    }
    this.confirmationRoleEnCours = true;
  }

  annulerConfirmationRole(): void {
    this.confirmationRoleEnCours = false;
  }

  confirmerChangementRole(): void {
    if (!this.membreSelectionne) {
      return;
    }

    this.adminService.changerRole(this.membreSelectionne.id, this.roleChoisi).subscribe({
      next: () => {
        this.messageSuccesMembre = 'Rôle mis à jour.';
        this.confirmationRoleEnCours = false;
        this.chargerMembres();
        this.fermerModaleMembre();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messagesErreursMembre = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.confirmationRoleEnCours = false;
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Blocage / déblocage ----------

  demanderConfirmationBlocage(action: 'bloquer' | 'debloquer'): void {
    if (!this.membreSelectionne) {
      return;
    }
    this.confirmationBlocageEnCours = {membre: this.membreSelectionne, action};
  }

  annulerConfirmationBlocage(): void {
    this.confirmationBlocageEnCours = null;
  }

  confirmerBlocage(): void {
    if (!this.confirmationBlocageEnCours) {
      return;
    }

    const {membre, action} = this.confirmationBlocageEnCours;
    const requete = action === 'bloquer'
      ? this.adminService.bloquerMembre(membre.id)
      : this.adminService.debloquerMembre(membre.id);

    requete.subscribe({
      next: () => {
        this.confirmationBlocageEnCours = null;
        this.fermerModaleMembre();
        this.chargerMembres();
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Suppression ----------

  demanderConfirmationSuppression(): void {
    if (!this.membreSelectionne) {
      return;
    }
    this.messageErreurSuppression = '';
    this.confirmationSuppressionEnCours = this.membreSelectionne;
  }

  annulerConfirmationSuppression(): void {
    this.confirmationSuppressionEnCours = null;
  }

  confirmerSuppression(): void {
    if (!this.confirmationSuppressionEnCours) {
      return;
    }

    this.adminService.supprimerMembre(this.confirmationSuppressionEnCours.id).subscribe({
      next: () => {
        this.confirmationSuppressionEnCours = null;
        this.fermerModaleMembre();
        this.chargerMembres();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messageErreurSuppression = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.confirmationSuppressionEnCours = null;
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Écriture d'un article ----------

  ouvrirModaleArticle(): void {
    this.modaleArticleOuverte = true;
    this.photoArticleSelectionnee = null;
    this.messagesErreursArticle = [];
    this.messageSuccesArticle = '';
    this.articleForm.reset();
  }

  fermerModaleArticle(): void {
    this.modaleArticleOuverte = false;
  }

  onPhotoArticleSelectionnee(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.photoArticleSelectionnee = input.files && input.files.length > 0 ? input.files[0] : null;
  }

  publierArticle(): void {
    if (this.publicationEnCours) {
      return;
    }

    this.messagesErreursArticle = [];
    this.messageSuccesArticle = '';

    if (!this.articleForm.valid) {
      const messages: string[] = [];
      if (this.articleForm.get('titre')?.errors?.['required']) {
        messages.push('Remplissez le titre.');
      }
      if (this.articleForm.get('contenu')?.errors?.['required']) {
        messages.push('Remplissez le contenu.');
      }
      this.messagesErreursArticle = messages;
      this.cdr.detectChanges();
      return;
    }

    const {titre, contenu} = this.articleForm.value;
    this.publicationEnCours = true;

    this.articleService.creer(titre, contenu, this.photoArticleSelectionnee).subscribe({
      next: () => {
        this.publicationEnCours = false;
        this.fermerModaleArticle();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.publicationEnCours = false;
        this.messagesErreursArticle = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }
}
