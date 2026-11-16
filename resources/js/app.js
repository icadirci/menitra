// Public site bundle (marketing + auth pages). Never loaded on menu pages or in the panels.
import './bootstrap';
import { startAlpine } from './lib/alpine';
import { startReveal } from './lib/reveal';

startAlpine();
startReveal();
