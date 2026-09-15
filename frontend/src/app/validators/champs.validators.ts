import {AbstractControl, ValidationErrors} from '@angular/forms';

// Champ optionnel : vide accepté. S'il est rempli, doit contenir exactement
// 10 chiffres (les espaces éventuels entre les chiffres sont ignorés).
export function telephoneValidator(control: AbstractControl): ValidationErrors | null {
  const valeur = control.value;
  if (!valeur) {
    return null;
  }
  const nettoye = valeur.replace(/\s+/g, '');
  return /^0\d{9}$/.test(nettoye) ? null : {telephoneInvalide: true};
}

// Vérifie les 5 règles de complexité du mot de passe. Renvoie un objet
// listant TOUTES les règles manquantes (pas juste la première trouvée),
// pour que le message FALC puisse tout afficher en une fois.
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
