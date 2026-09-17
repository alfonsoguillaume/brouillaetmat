import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {Router, RouterLink} from '@angular/router';
import {AuthService} from '../services/auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-connexion-component',
  styleUrl: './connexion-component.css',
  templateUrl: './connexion-component.html',
})
export class ConnexionComponent {
  private authService = inject(AuthService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  email: string = '';
  password: string = '';
  messageErreur: string = '';
  afficherMotDePasse: boolean = false;

  onSubmit(): void {
    this.messageErreur = '';

    this.authService.login(this.email, this.password).subscribe({
      next: () => {
        this.router.navigate(['/']);
      },
      error: (err) => {
        const message = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.messageErreur = this.traduireMessage(message);
        this.cdr.detectChanges();
      }
    });
  }

  /**
   * Symfony renvoie certains messages d'erreur en anglais par défaut.
   * On les traduit ici pour un affichage cohérent en français.
   */
  private traduireMessage(message: string): string {
    if (message === 'Invalid credentials.') {
      return 'Email ou mot de passe incorrect.';
    }

    // Ex: "Too many failed login attempts, please try again in 15 minutes."
    const correspondance = message.match(/Too many failed login attempts.*?(\d+)\s*minute/i);
    if (correspondance) {
      const minutes = correspondance[1];
      return `Trop de tentatives de connexion. Réessayez dans ${minutes} minutes.`;
    }

    return message;
  }
}
