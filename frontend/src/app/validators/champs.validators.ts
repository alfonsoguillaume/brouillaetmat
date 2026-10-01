import {AbstractControl, ValidationErrors} from '@angular/forms';

// champ optionnel, vide accepté. sinon 10 chiffres (espaces ignorés)
export function telephoneValidator(control: AbstractControl): ValidationErrors | null {
  const valeur = control.value;
  if (!valeur) {
    return null;
  }
  const nettoye = valeur.replace(/\s+/g, '');
  return /^0\d{9}$/.test(nettoye) ? null : {telephoneInvalide: true};
}

// vérifie les 5 règles du mot de passe, renvoie toutes les règles manquantes (pas juste la 1ere) pour le message FALC
export function motDePasseValidator(control: AbstractControl): ValidationErrors | null {
  const valeur = control.value;
  if (!valeur) {
    return null; // champ vide : Validators.required s'en charge séparément
  }

  const erreurs: ValidationErrors = {};
  if (valeur.length < 12) erreurs['longueur'] = true;
  if (!/[A-Z]/.test(valeur)) erreurs['majuscule'] = true;
  if (!/[a-z]/.test(valeur)) erreurs['minuscule'] = true;
  if (!/\d/.test(valeur)) erreurs['chiffre'] = true;
  if (!/[^A-Za-z0-9]/.test(valeur)) erreurs['special'] = true;

  return Object.keys(erreurs).length > 0 ? erreurs : null;
}
