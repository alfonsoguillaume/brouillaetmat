import {ChangeDetectorRef, Component, inject, OnInit} from '@angular/core';
import {DatePipe} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {ProfilService} from '../services/profil.service';
import {motDePasseValidator, telephoneValidator} from '../validators/champs.validators';

@Component({
  selector: 'app-profil-component',
  standalone: true,
  imports: [ReactiveFormsModule, DatePipe],
  templateUrl: './profil-component.html',
  styleUrl: './profil-component.css',
})
export class ProfilComponent implements OnInit {
  private fb = inject(FormBuilder);
  private profilService = inject(ProfilService);
  private cdr = inject(ChangeDetectorRef);

  chargementEnCours = true;
  isMineur = false;

  // Infos non modifiables, affichées à part (pas dans un formulaire).
  email = '';
  dateNaissance = '';

  messagesErreursProfil: string[] = [];
  messageSuccesProfil = '';

  messagesErreursMotDePasse: string[] = [];
  messageSuccesMotDePasse = '';

  private nomsChampsProfil: { [cle: string]: string } = {
    nom: 'le nom',
    prenom: 'le prénom',
    pseudo: 'le pseudo',
    telephone: 'le téléphone',
    emailTuteur: "l'e-mail du tuteur légal",
  };

  profilForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
    prenom: ['', Validators.required],
    pseudo: ['', Validators.required],
    telephone: ['', telephoneValidator],
    emailTuteur: [''],
  });

  motDePasseForm: FormGroup = this.fb.group({
    motDePasseActuel: ['', Validators.required],
    nouveauMotDePasse: ['', [Validators.required, motDePasseValidator]],
    confirmationNouveauMotDePasse: ['', Validators.required],
  });

  ngOnInit(): void {
    this.profilService.getProfil().subscribe({
      next: (profil) => {
        this.email = profil.email;
        this.dateNaissance = profil.date_naissance;

        this.profilForm.patchValue({
          nom: profil.nom,
          prenom: profil.prenom,
          pseudo: profil.pseudo,
          telephone: profil.telephone ?? '',
          emailTuteur: profil.email_tuteur ?? '',
        });

        // Recalcule si le membre est encore mineur, pour exiger (ou non)
        // l'e-mail du tuteur, comme à l'inscription.
        this.isMineur = this.calculerAge(profil.date_naissance) < 18;
        const tuteurControl = this.profilForm.get('emailTuteur');
        if (this.isMineur) {
          tuteurControl?.setValidators([Validators.required, Validators.email]);
        } else {
          tuteurControl?.setValidators([]);
        }
        tuteurControl?.updateValueAndValidity();

        this.chargementEnCours = false;
        this.cdr.detectChanges();
      },
    });
  }

  private calculerAge(dateNaissance: string): number {
    const naissance = new Date(dateNaissance);
    const aujourdhui = new Date();
    let age = aujourdhui.getFullYear() - naissance.getFullYear();
    const diffMois = aujourdhui.getMonth() - naissance.getMonth();
    if (diffMois < 0 || (diffMois === 0 && aujourdhui.getDate() < naissance.getDate())) {
      age--;
    }
    return age;
  }

  private construireMessagesErreursProfil(): string[] {
    const messages: string[] = [];

    for (const cle in this.nomsChampsProfil) {
      const controle = this.profilForm.get(cle);
      if (!controle || controle.valid) {
        continue;
      }

      const nomLisible = this.nomsChampsProfil[cle];

      if (controle.errors?.['required']) {
        messages.push(`Remplissez ${nomLisible}.`);
      } else if (controle.errors?.['email']) {
        messages.push(`Écrivez ${nomLisible} avec un @, par exemple : nom@exemple.fr.`);
      } else if (controle.errors?.['telephoneInvalide']) {
        messages.push('Écrivez le téléphone avec 10 chiffres, en commençant par 0. Exemple : 0612345678.');
      }
    }

    return messages;
  }

  enregistrerProfil(): void {
    this.messagesErreursProfil = [];
    this.messageSuccesProfil = '';

    if (!this.profilForm.valid) {
      this.messagesErreursProfil = this.construireMessagesErreursProfil();
      this.cdr.detectChanges();
      return;
    }

    const valeurs = this.profilForm.value;
    this.profilService.modifierProfil({
      nom: valeurs.nom,
      prenom: valeurs.prenom,
      pseudo: valeurs.pseudo,
      telephone: valeurs.telephone || null,
      email_tuteur: valeurs.emailTuteur || null,
    }).subscribe({
      next: () => {
        this.messageSuccesProfil = 'Profil mis à jour.';
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messagesErreursProfil = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  changerMotDePasse(): void {
    this.messagesErreursMotDePasse = [];
    this.messageSuccesMotDePasse = '';

    const controlePassword = this.motDePasseForm.get('nouveauMotDePasse');

    if (!this.motDePasseForm.valid) {
      const messages: string[] = [];

      if (this.motDePasseForm.get('motDePasseActuel')?.errors?.['required']) {
        messages.push('Remplissez votre mot de passe actuel.');
      }
      if (controlePassword?.errors?.['required']) {
        messages.push('Remplissez le nouveau mot de passe.');
      } else if (controlePassword?.errors) {
        if (controlePassword.errors['longueur']) messages.push('Le nouveau mot de passe doit avoir au moins 12 caractères.');
        if (controlePassword.errors['majuscule']) messages.push('Le nouveau mot de passe doit avoir au moins une majuscule.');
        if (controlePassword.errors['minuscule']) messages.push('Le nouveau mot de passe doit avoir au moins une minuscule.');
        if (controlePassword.errors['chiffre']) messages.push('Le nouveau mot de passe doit avoir au moins un chiffre.');
        if (controlePassword.errors['special']) messages.push('Le nouveau mot de passe doit avoir au moins un caractère spécial.');
      }
      if (this.motDePasseForm.get('confirmationNouveauMotDePasse')?.errors?.['required']) {
        messages.push('Remplissez la vérification du mot de passe.');
      }

      this.messagesErreursMotDePasse = messages;
      this.cdr.detectChanges();
      return;
    }

    const valeurs = this.motDePasseForm.value;

    if (valeurs.nouveauMotDePasse !== valeurs.confirmationNouveauMotDePasse) {
      this.messagesErreursMotDePasse = ['Les deux mots de passe ne sont pas identiques.'];
      this.cdr.detectChanges();
      return;
    }

    this.profilService.changerMotDePasse({
      mot_de_passe_actuel: valeurs.motDePasseActuel,
      nouveau_mot_de_passe: valeurs.nouveauMotDePasse,
    }).subscribe({
      next: () => {
        this.messageSuccesMotDePasse = 'Mot de passe modifié avec succès.';
        this.motDePasseForm.reset();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messagesErreursMotDePasse = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }
}
