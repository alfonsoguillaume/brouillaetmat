import { ComponentFixture, TestBed } from '@angular/core/testing';
import { TournoisComponent } from './tournois-component';

describe('TournoisComponent', () => {
  let component: TournoisComponent;
  let fixture: ComponentFixture<TournoisComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TournoisComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TournoisComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
