import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {HttpClient} from '@angular/common/http';
import {Router} from '@angular/router';
import {motDePasseValidator, telephoneValidator} from '../validators/champs.validators';

@Component({
  selector: 'app-inscription',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './inscription-component.html',
  styleUrl: './inscription-component.css'
})
export class InscriptionComponent {
  private fb = inject(FormBuilder);
  private http = inject(HttpClient);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  private apiUrl = 'http://localhost:8000/api';

  isMineur = false;
  messageErreur = '';
  afficherMotDePasse = false;
  messagesErreursFormulaire: string[] = [];

  // Nom lisible de chaque champ, utilisé pour construire des messages clairs.
  private nomsChamps: { [cle: string]: string } = {
    nom: 'le nom',
    prenom: 'le prénom',
    pseudo: 'le pseudo',
    email: "l'e-mail",
    dateNaissance: 'la date de naissance',
    emailTuteur: "l'e-mail du tuteur légal",
    telephone: 'le téléphone',
    confirmPassword: 'la vérification du mot de passe'
  };

  inscriptionForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
    prenom: ['', Validators.required],
    pseudo: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    dateNaissance: ['', Validators.required],
    emailTuteur: [''],
    telephone: ['', telephoneValidator],
    password: ['', [Validators.required, motDePasseValidator]],
    confirmPassword: ['', Validators.required]
  });

  checkAge(): void {
    const dateValeur = this.inscriptionForm.get('dateNaissance')?.value;
    if (!dateValeur) return;

    const birthDate = new Date(dateValeur);
    const today = new Date();

    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    this.isMineur = age < 18;

    const tuteurControl = this.inscriptionForm.get('emailTuteur');
    if (this.isMineur) {
      tuteurControl?.setValidators([Validators.required, Validators.email]);
    } else {
      tuteurControl?.clearValidators();
      tuteurControl?.setValue('');
    }
    tuteurControl?.updateValueAndValidity();
  }

  /**
   * Construit une liste de messages simples et précis, un par problème détecté.
   * Principe FALC : une phrase courte, une seule idée, pas d'ambiguïté.
   */
  private construireMessagesErreurs(): string[] {
    const messages: string[] = [];

    // Cas particulier du mot de passe : plusieurs règles peuvent manquer en
    // même temps, on les liste toutes plutôt qu'une seule à la fois.
    const passwordControle = this.inscriptionForm.get('password');
    if (passwordControle && passwordControle.invalid) {
      if (passwordControle.errors?.['required']) {
        messages.push('Remplissez le mot de passe.');
      } else {
        if (passwordControle.errors?.['longueur']) {
          messages.push('Le mot de passe doit avoir au moins 12 caractères.');
        }
        if (passwordControle.errors?.['majuscule']) {
          messages.push('Le mot de passe doit avoir au moins une majuscule (A, B, C...).');
        }
        if (passwordControle.errors?.['minuscule']) {
          messages.push('Le mot de passe doit avoir au moins une minuscule (a, b, c...).');
        }
        if (passwordControle.errors?.['chiffre']) {
          messages.push('Le mot de passe doit avoir au moins un chiffre (0 à 9).');
        }
        if (passwordControle.errors?.['special']) {
          messages.push('Le mot de passe doit avoir au moins un caractère spécial, par exemple : ! ? # @.');
        }
      }
    }

    // Tous les autres champs : un seul type d'erreur possible à la fois.
    for (const cle in this.nomsChamps) {
      const controle = this.inscriptionForm.get(cle);
      if (!controle || controle.valid) {
        continue;
      }

      const nomLisible = this.nomsChamps[cle];

      if (controle.errors?.['required']) {
        messages.push(`Remplissez ${nomLisible}.`);
      } else if (controle.errors?.['email']) {
        messages.push(`Écrivez ${nomLisible} avec un @, par exemple : nom@exemple.fr.`);
      } else if (controle.errors?.['telephoneInvalide']) {
        messages.push(`Écrivez le téléphone avec 10 chiffres, en commençant par 0. Exemple : 0612345678.`);
      }
    }

    return messages;
  }

  onSubmit(): void {
    this.messageErreur = '';
    this.messagesErreursFormulaire = [];

    if (!this.inscriptionForm.valid) {
      this.messagesErreursFormulaire = this.construireMessagesErreurs();
      this.cdr.detectChanges();
      return;
    }

    const valeurs = this.inscriptionForm.value;

    if (valeurs.password !== valeurs.confirmPassword) {
      this.messagesErreursFormulaire = ['Les deux mots de passe ne sont pas identiques.'];
      this.cdr.detectChanges();
      return;
    }

    if (valeurs.emailTuteur && valeurs.email.toLowerCase() === valeurs.emailTuteur.toLowerCase()) {
      this.messagesErreursFormulaire = ["L'e-mail du membre et l'e-mail du tuteur doivent être différents."];
      this.cdr.detectChanges();
      return;
    }

    // Conversion camelCase (Angular) -> snake_case (attendu par l'API Symfony)
    const payload = {
      nom: valeurs.nom,
      prenom: valeurs.prenom,
      pseudo: valeurs.pseudo,
      email: valeurs.email,
      date_naissance: valeurs.dateNaissance,
      email_tuteur: valeurs.emailTuteur || null,
      telephone: valeurs.telephone || null,
      password: valeurs.password
    };

    this.http.post(`${this.apiUrl}/inscription`, payload).subscribe({
      next: () => {
        this.router.navigate(['/connexion']);
      },
      error: (err) => {
        this.messageErreur = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      }
    });
  }
}
