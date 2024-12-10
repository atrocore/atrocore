import 'driver.js/dist/driver.css'
import './styles/style.css';

import {driver} from 'driver.js'

import {Language} from './utils/Language';
import {UserData} from './utils/UserData';
import {Notifier} from './utils/Notifier';
import {LayoutManager} from "./utils/LayoutManager";
import {Metadata} from "./utils/Metadata";
import {ModelFactory} from "./utils/ModelFactory";

import JobManagerIcon from './components/icons/QueueManagerIcon.svelte';
import UpdatePanel from "./components/panels/UpdatePanel.svelte";
import LayoutComponent from "./components/admin/layouts/LayoutComponent.svelte";
import ApiRequestComponent from "./components/admin/api-request/ApiRequestComponent.svelte";

window.SvelteLanguage = Language;
window.SvelteUserData = UserData;
window.SvelteNotifier = Notifier;
window.SvelteLayoutManager = LayoutManager;
window.SvelteMetadata = Metadata;
window.SvelteModelFactory = ModelFactory;
window.driver = driver;

export {UpdatePanel, JobManagerIcon, LayoutComponent, ApiRequestComponent};
