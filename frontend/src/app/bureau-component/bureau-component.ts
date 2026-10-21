import { Component, inject } from '@angular/core';
import { AuthService } from '../services/auth.service';

@Component({
  imports: [],
  selector: 'app-bureau-component',
  styleUrl: './bureau-component.css',
  templateUrl: './bureau-component.html',
})
export class BureauComponent {
  // pour cacher le téléphone aux non connectés
  protected readonly auth = inject(AuthService);
}
