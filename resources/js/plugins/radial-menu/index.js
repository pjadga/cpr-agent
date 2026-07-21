import RadialMenuHost from './RadialMenuHost.vue';
import {longPress} from './longPress';
import {openRadial, closeRadial, useRadialMenu} from './useRadialMenu';

/**
 * Radial "Mobile Select" menu: a reusable press-and-hold contextual menu.
 *
 *   app.use(RadialMenu)
 *
 * Registers the global <radial-menu-host> component (mount it once at app root)
 * and the v-long-press directive. Open a menu imperatively via openRadial(),
 * or declaratively by attaching v-long-press="() => actions" to any element.
 */
export const RadialMenu = {
    install(app) {
        app.component('RadialMenuHost', RadialMenuHost);
        app.directive('long-press', longPress);
    },
};

export {RadialMenuHost, longPress, openRadial, closeRadial, useRadialMenu};
export default RadialMenu;
