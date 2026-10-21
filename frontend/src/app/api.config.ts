// adresse de l'API : locale sur le PC, sinon le site en ligne
export const API_URL = window.location.hostname === 'localhost'
  ? 'http://localhost:8000'
  : 'https://api.brouillaetmat.fr';
