import './styles/style.css';

import QueueManagerIcon from './components/icons/QueueManagerIcon.svelte';
import { Language } from './utils/Language';
import { UserData } from './utils/UserData';
import { Notifier } from './utils/Notifier';

window.SvelteLanguage = Language;
window.SvelteUserData = UserData;
window.SvelteNotifier = Notifier;

export {QueueManagerIcon};
