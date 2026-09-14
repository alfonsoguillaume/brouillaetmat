import { ComponentFixture, TestBed } from '@angular/core/testing';
import { OublieComponent } from './oublie-component';

describe('OublieComponent', () => {
  let component: OublieComponent;
  let fixture: ComponentFixture<OublieComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [OublieComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(OublieComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
