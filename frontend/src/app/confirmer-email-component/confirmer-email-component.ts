import {ChangeDetectorRef, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {ProfilService} from '../services/profil.service';
import {AuthService} from '../services/auth.service';

@Component({
  selector: 'app-confirmer-email-component',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './confirmer-email-component.html',
  styleUrl: './confirmer-email-component.css',
})
export class ConfirmerEmailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private profilService = inject(ProfilService);
  private authService = inject(AuthService);
  private cdr = inject(ChangeDetectorRef);

  chargementEnCours = true;
  succes = false;
  message = '';

  ngOnInit(): void {
    const token = this.route.snapshot.paramMap.get('token');

    if (!token) {
      this.chargementEnCours = false;
      this.message = 'Lien incomplet.';
      return;
    }

    // Appelée automatiquement dès l'arrivée sur la page — pas besoin
    // d'action de la part de l'utilisateur, le lien lui-même est la preuve.
    this.profilService.confirmerEmail(token).subscribe({
      next: (data) => {
        this.chargementEnCours = false;
        this.succes = true;
        this.message = data.message;
        // L'ancienne session (liée à l'ancien email) n'a plus de sens
        // maintenant que l'email a réellement changé en base — on
        // déconnecte automatiquement pour forcer une reconnexion propre.
        this.authService.logout();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.chargementEnCours = false;
        this.succes = false;
        this.message = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }
}
