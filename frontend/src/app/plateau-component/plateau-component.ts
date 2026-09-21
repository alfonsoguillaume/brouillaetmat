import {
  AfterViewInit,
  Component,
  ElementRef,
  EventEmitter,
  Input,
  OnChanges,
  Output,
  SimpleChanges,
  ViewChild
} from '@angular/core';
import {Chessground} from '@lichess-org/chessground';
import type {Api} from '@lichess-org/chessground/api';
import type {Config} from '@lichess-org/chessground/config';
import type {Key} from '@lichess-org/chessground/types';
import {Chess} from 'chess.js';

const POSITION_DEPART = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

type ResultatFin = { resultat: 'blanc' | 'noir' | 'nul'; motif: string };

@Component({
  selector: 'app-plateau',
  standalone: true,
  imports: [],
  templateUrl: './plateau-component.html',
  styleUrl: './plateau-component.css',
})
export class PlateauComponent implements AfterViewInit, OnChanges {
  @ViewChild('boardElement') boardElement!: ElementRef<HTMLElement>;

  @Input() fen: string = POSITION_DEPART;
  @Input() jeSuisBlanc: boolean = true;
  @Input() monTour: boolean = false;

  // Le coup ET, s'il y a lieu, le résultat de fin de partie voyagent
  // ENSEMBLE dans le même évènement — pour que le parent enregistre
  // d'abord le coup, PUIS déclenche la fin de partie seulement une fois
  // le coup confirmé enregistré (évite la course entre les 2 requêtes).
  @Output() coupJoue = new EventEmitter<{ fen: string; coup: string; finPartie: ResultatFin | null }>();

  // Utilisé uniquement quand on REÇOIT un coup de l'adversaire (pas de
  // coup à "chaîner" dans ce cas — le coup est déjà enregistré côté
  // serveur puisqu'on vient de le recevoir via le rafraîchissement).
  @Output() partieTerminee = new EventEmitter<ResultatFin>();

  private api: Api | null = null;
  private chess = new Chess();

  ngAfterViewInit(): void {
    this.chess.load(this.fen);
    this.api = Chessground(this.boardElement.nativeElement, this.construireConfig());

    const fin = this.detecterFinDePartie();
    if (fin) {
      this.partieTerminee.emit(fin);
    }
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (!this.api) {
      return;
    }

    if (changes['fen'] && !changes['fen'].firstChange) {
      this.chess.load(this.fen);

      const fin = this.detecterFinDePartie();
      if (fin) {
        this.partieTerminee.emit(fin);
      }
    }

    this.api.set(this.construireConfig());
  }

  private construireConfig(): Config {
    const dests = this.monTour ? this.calculerDestinations() : new Map();

    return {
      fen: this.fen,
      orientation: this.jeSuisBlanc ? 'white' : 'black',
      turnColor: this.chess.turn() === 'w' ? 'white' : 'black',
      check: this.chess.inCheck(),
      movable: {
        free: false,
        color: this.monTour ? (this.jeSuisBlanc ? 'white' : 'black') : undefined,
        dests,
        events: {
          after: (orig: Key, dest: Key) => this.onDeplacement(orig, dest),
        },
      },
    };
  }

  private calculerDestinations(): Map<Key, Key[]> {
    const dests = new Map<Key, Key[]>();
    const coupsLegaux = this.chess.moves({verbose: true});

    for (const coup of coupsLegaux) {
      const departs = dests.get(coup.from as Key) ?? [];
      departs.push(coup.to as Key);
      dests.set(coup.from as Key, departs);
    }

    return dests;
  }

  private onDeplacement(orig: Key, dest: Key): void {
    const resultat = this.chess.move({from: orig, to: dest, promotion: 'q'});
    if (!resultat) {
      return;
    }

    const nouveauFen = this.chess.fen();
    const finPartie = this.detecterFinDePartie();

    // Un seul évènement, avec finPartie inclus dedans — le parent attend
    // la confirmation d'enregistrement du coup avant d'agir dessus.
    this.coupJoue.emit({fen: nouveauFen, coup: resultat.san, finPartie});

    this.api?.set({
      fen: nouveauFen,
      check: this.chess.inCheck(),
      movable: {color: undefined, dests: new Map()},
    });
  }

  // chess.js sait reconnaître nativement toutes les fins de partie
  // classiques — on se contente de l'interroger. Renvoie l'info (sans
  // rien émettre elle-même) pour laisser l'appelant décider du moment.
  private detecterFinDePartie(): ResultatFin | null {
    if (this.chess.isCheckmate()) {
      const gagnant = this.chess.turn() === 'w' ? 'noir' : 'blanc';
      return {resultat: gagnant, motif: 'Échec et mat'};
    }
    if (this.chess.isStalemate()) {
      return {resultat: 'nul', motif: 'Pat'};
    }
    if (this.chess.isThreefoldRepetition()) {
      return {resultat: 'nul', motif: 'Répétition de position'};
    }
    if (this.chess.isInsufficientMaterial()) {
      return {resultat: 'nul', motif: 'Matériel insuffisant pour mater'};
    }
    if (this.chess.isDraw()) {
      return {resultat: 'nul', motif: 'Règle des 50 coups'};
    }
    return null;
  }
}
