import { ComponentFixture, TestBed } from '@angular/core/testing';
import { JouerComponent } from './jouer-component';

describe('JouerComponent', () => {
  let component: JouerComponent;
  let fixture: ComponentFixture<JouerComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [JouerComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(JouerComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
