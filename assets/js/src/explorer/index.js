import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import App from './App';
import './style.scss';

domReady(() => {
    const rootElement = document.getElementById('bs-fabric-explorer-root');

    if (!rootElement) {
        return;
    }

    createRoot(rootElement).render(<App />);
});
