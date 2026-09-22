import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {DatePipe} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {DocumentAdmin, DocumentService, Dossier} from '../services/document.service';

@Component({
  selector: 'app-document-component',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './document-component.html',
  styleUrl: './document-component.css',
})
export class DocumentComponent {
  private documentService = inject(DocumentService);
  private fb = inject(FormBuilder);
  private cdr = inject(ChangeDetectorRef);

  // Documents "à la racine" (hors de tout dossier) — toujours visibles.
  documents: DocumentAdmin[] = [];
  dossiers: Dossier[] = [];

  constructor() {
    this.chargerDocuments();
    this.chargerDossiers();
  }

  chargerDocuments(): void {
    this.documentService.liste(null).subscribe({
      next: (data) => {
        this.documents = data;
        this.cdr.detectChanges();
      }
    });
  }

  chargerDossiers(): void {
    this.documentService.listeDossiers().subscribe({
      next: (data) => {
        this.dossiers = data;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Détail d'un dossier (modale) ----------

  dossierSelectionne: Dossier | null = null;
  documentsDossier: DocumentAdmin[] = [];

  ouvrirDossier(dossier: Dossier): void {
    this.dossierSelectionne = dossier;
    this.documentsDossier = [];
    this.documentService.liste(dossier.id).subscribe({
      next: (data) => {
        this.documentsDossier = data;
        this.cdr.detectChanges();
      }
    });
  }

  fermerDossier(): void {
    this.dossierSelectionne = null;
  }

  // ---------- Créer un dossier ----------

  modaleDossierOuverte = false;
  nomNouveauDossier = '';
  messageErreurDossier = '';
  creationDossierEnCours = false;

  ouvrirModaleDossier(): void {
    this.nomNouveauDossier = '';
    this.messageErreurDossier = '';
    this.modaleDossierOuverte = true;
  }

  fermerModaleDossier(): void {
    this.modaleDossierOuverte = false;
  }

  onNomDossierChange(valeur: string): void {
    this.nomNouveauDossier = valeur;
  }

  creerDossier(): void {
    if (this.creationDossierEnCours) {
      return;
    }

    if (!this.nomNouveauDossier.trim()) {
      this.messageErreurDossier = 'Remplissez le nom du dossier.';
      this.cdr.detectChanges();
      return;
    }

    this.creationDossierEnCours = true;

    this.documentService.creerDossier(this.nomNouveauDossier.trim()).subscribe({
      next: () => {
        this.creationDossierEnCours = false;
        this.fermerModaleDossier();
        this.chargerDossiers();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.creationDossierEnCours = false;
        this.messageErreurDossier = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Supprimer un dossier (cascade : vide son contenu aussi) ----------

  confirmationSuppressionDossierEnCours: Dossier | null = null;
  messageErreurSuppressionDossier = '';

  demanderConfirmationSuppressionDossier(dossier: Dossier): void {
    this.messageErreurSuppressionDossier = '';
    this.confirmationSuppressionDossierEnCours = dossier;
  }

  annulerConfirmationSuppressionDossier(): void {
    this.confirmationSuppressionDossierEnCours = null;
  }

  confirmerSuppressionDossier(): void {
    if (!this.confirmationSuppressionDossierEnCours) {
      return;
    }

    this.documentService.supprimerDossier(this.confirmationSuppressionDossierEnCours.id).subscribe({
      next: () => {
        this.confirmationSuppressionDossierEnCours = null;
        this.fermerDossier();
        this.chargerDossiers();
        this.chargerDocuments();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messageErreurSuppressionDossier = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Télécharger ----------

  telechargerDocument(doc: DocumentAdmin): void {
    this.documentService.telecharger(doc.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const lien = window.document.createElement('a');
        lien.href = url;
        lien.download = doc.nom;
        lien.click();
        window.URL.revokeObjectURL(url);
      }
    });
  }

  // ---------- Ajouter un document (avec choix explicite du dossier) ----------

  modaleAjoutOuverte = false;
  fichierSelectionne: File | null = null;
  dossierChoisiPourAjout = ''; // '' = racine
  messagesErreursAjout: string[] = [];
  ajoutEnCours = false;

  ajoutForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
  });

  ouvrirModaleAjout(): void {
    this.modaleAjoutOuverte = true;
    this.fichierSelectionne = null;
    // Si on ouvre l'ajout depuis l'intérieur d'un dossier (modale de
    // détail déjà ouverte), on pré-sélectionne ce dossier — sinon racine.
    this.dossierChoisiPourAjout = this.dossierSelectionne ? this.dossierSelectionne.id.toString() : '';
    this.messagesErreursAjout = [];
    this.ajoutForm.reset();
  }

  fermerModaleAjout(): void {
    this.modaleAjoutOuverte = false;
  }

  onFichierSelectionne(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.fichierSelectionne = input.files && input.files.length > 0 ? input.files[0] : null;
  }

  onDossierAjoutChange(valeur: string, menu: HTMLDetailsElement): void {
    this.dossierChoisiPourAjout = valeur;
    menu.open = false; // referme le menu après le choix
  }

  texteDossierChoisiPourAjout(): string {
    if (!this.dossierChoisiPourAjout) {
      return 'Racine (aucun dossier)';
    }
    const d = this.dossiers.find(x => x.id.toString() === this.dossierChoisiPourAjout);
    return d ? '📁 ' + d.nom : 'Racine (aucun dossier)';
  }

  ajouterDocument(): void {
    if (this.ajoutEnCours) {
      return;
    }

    this.messagesErreursAjout = [];

    const messages: string[] = [];
    if (this.ajoutForm.get('nom')?.errors?.['required']) {
      messages.push('Remplissez le nom du document.');
    }
    if (!this.fichierSelectionne) {
      messages.push('Sélectionnez un fichier.');
    }
    if (messages.length > 0) {
      this.messagesErreursAjout = messages;
      this.cdr.detectChanges();
      return;
    }

    const {nom} = this.ajoutForm.value;
    const dossierId = this.dossierChoisiPourAjout ? +this.dossierChoisiPourAjout : null;
    this.ajoutEnCours = true;

    this.documentService.ajouter(nom, this.fichierSelectionne!, dossierId).subscribe({
      next: () => {
        this.ajoutEnCours = false;
        this.fermerModaleAjout();
        this.chargerDocuments();
        this.chargerDossiers();
        // Si on avait ajouté dans le dossier actuellement ouvert, on
        // rafraîchit aussi sa liste pour voir le nouveau fichier tout de suite.
        if (this.dossierSelectionne) {
          this.ouvrirDossier(this.dossierSelectionne);
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.ajoutEnCours = false;
        this.messagesErreursAjout = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Supprimer un document ----------

  confirmationSuppressionEnCours: DocumentAdmin | null = null;

  demanderConfirmationSuppression(doc: DocumentAdmin): void {
    this.confirmationSuppressionEnCours = doc;
  }

  annulerConfirmationSuppression(): void {
    this.confirmationSuppressionEnCours = null;
  }

  confirmerSuppression(): void {
    if (!this.confirmationSuppressionEnCours) {
      return;
    }

    this.documentService.supprimer(this.confirmationSuppressionEnCours.id).subscribe({
      next: () => {
        this.confirmationSuppressionEnCours = null;
        this.chargerDocuments();
        this.chargerDossiers();
        if (this.dossierSelectionne) {
          this.ouvrirDossier(this.dossierSelectionne);
        }
        this.cdr.detectChanges();
      }
    });
  }
}
