import './bootstrap';
import {createApp, ref, computed, watch, onMounted, nextTick} from 'vue';

createApp({
    setup() {
        const config = ref({
            baseUrl: 'https://foundry-api.surrealdisco.co.uk',
            apiKey: 'testapikey',
            clientId: '',
            prettify: true,
        });

        const clientIdReadOnly = ref(false);
        const sidebarOpen = ref(true);
        const sidebarSections = ref([]);
        const activeSection = ref(null);
        const activeSubsection = ref(null);

        const defaultPayloads = {
            selectEntities: '{\n  "uuids": [],\n  "name": "",\n  "data": {},\n  "overwrite": true\n}',
            createEntity: '{\n  "type": "Actor",\n  "data": {\n    "name": "New API Actor",\n    "type": "character",\n    "img": "icons/svg/mystery-man.svg"\n  }\n}',
            updateEntity: '{}',
            executeMacroArgs: '{}',
            makeRoll: '{\n  "formula": "2d6+3",\n  "createChatMessage": true\n}',
            startEncounter: '{\n  "tokens": [],\n  "startWithSelected": true,\n  "rollNPC": true\n}',
            addToEncounter: '{\n  "uuids": [],\n  "selected": false,\n  "rollInitiative": true\n}',
            removeFromEncounter: '{\n  "uuids": [],\n  "selected": false\n}',
            increaseAttribute: '{\n  "attribute": "system.attributes.hp.value",\n  "amount": 10\n}',
            giveItem: '{\n  "itemUuid": "Item.XYZ",\n  "toUuid": "Actor.ABC",\n  "quantity": 1\n}'
        };

        const params = ref({
            getStructure: {clientId: ''},
            getContentsByPath: {path: '', clientId: ''},
            searchEntities: {query: '', filter: '', clientId: ''},
            selectEntities: {payload: defaultPayloads.selectEntities, clientId: ''},
            getSelectedEntities: {clientId: ''},
            getEntity: {uuid: '', selected: '', actor: '', clientId: ''},
            createEntity: {payload: defaultPayloads.createEntity, clientId: ''},
            updateEntity: {payload: defaultPayloads.updateEntity, uuid: '', selected: '', actor: '', clientId: ''},
            deleteEntity: {uuid: '', selected: '', clientId: ''},
            getMacros: {clientId: ''},
            executeMacro: {uuid: '', args: defaultPayloads.executeMacroArgs, clientId: ''},
            getSheet: {
                uuid: '',
                selected: '',
                actor: '',
                format: 'json',
                scale: null,
                tab: null,
                darkMode: null,
                clientId: ''
            },
            getRolls: {limit: 20, clientId: ''},
            getLastRoll: {clientId: ''},
            makeRoll: {payload: defaultPayloads.makeRoll, clientId: ''},
            getEncounters: {clientId: ''},
            startEncounter: {payload: defaultPayloads.startEncounter, clientId: ''},
            nextTurnInEncounter: {encounterId: '', clientId: ''},
            nextRoundInEncounter: {encounterId: '', clientId: ''},
            previousTurnInEncounter: {encounterId: '', clientId: ''},
            previousRoundInEncounter: {encounterId: '', clientId: ''},
            endEncounter: {encounterId: '', clientId: ''},
            addToEncounter: {payload: defaultPayloads.addToEncounter, encounterId: '', clientId: ''},
            removeFromEncounter: {payload: defaultPayloads.removeFromEncounter, encounterId: '', clientId: ''},
            markEntityAsDefeated: {uuid: '', selected: '', clientId: ''},
            decreaseAttribute: {
                attribute: 'system.attributes.hp.value',
                amount: 1,
                uuid: '',
                selected: '',
                clientId: ''
            },
            increaseAttribute: {payload: defaultPayloads.increaseAttribute, clientId: ''},
            giveItem: {payload: defaultPayloads.giveItem, clientId: ''},
        });

        const response = ref(null);
        const error = ref(null);
        const loading = ref(false);
        const showResponseModal = ref(false);

        // The JSON syntax highlighting and indentation functions have been moved to the PHP side
        // in the FoundryClient class. The frontend now uses the HTML formatted output directly
        // from the backend when available.

        // Updated computed property for prettified and styled JSON/HTML output
        const responsePretty = computed(() => {
            if (response.value) {
                // Handle HTML content directly if present (e.g., from getSheet with format=html)
                if (response.value.html_content) {
                    // Render HTML content directly within a styled container
                    return `<div class="p-4 bg-white text-gray-900 rounded-md shadow-inner overflow-x-auto">${response.value.html_content}</div>`;
                }

                let outputHtml = '';
                if (response.value._prettified && response.value._html_formatted) {
                    // If prettified by backend and HTML formatting is available, use it directly
                    outputHtml = response.value._html_formatted;

                    // Remove the _html_formatted property from the displayed data
                    const displayData = { ...response.value };
                    delete displayData._html_formatted;
                } else if (response.value._prettified) {
                    // If prettified by backend but no HTML formatting, format for clickable IDs and apply syntax highlighting
                    outputHtml = formatPrettifiedResponse(response.value);
                } else {
                    // If NOT prettified by backend, just stringify the JSON with basic HTML escaping
                    const plainJsonString = JSON.stringify(response.value, null, 2);
                    outputHtml = plainJsonString
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                }

                // Wrap in pre-code for general code styling and ensure line wrapping
                // Add `text-gray-200` for default text color in dark background
                // Add a header/title to the JSON output for better user experience
                return `
                <div class="json-response-wrapper">
                    <div class="json-response-header">
                        <span class="json-response-title">DATA STREAM OUTPUT</span>
                        <span class="json-response-info">${new Date().toLocaleTimeString()}</span>
                    </div>
                    <pre class="whitespace-pre-wrap font-mono text-sm leading-relaxed p-4 bg-gray-900 rounded-md overflow-x-auto text-gray-200 json-output-container"><code class="language-json">${outputHtml}</code></pre>
                </div>`;
            }
            return '';
        });

        // Function to format prettified responses with clickable IDs/UUIDs
        // This is now a fallback for when the server doesn't provide HTML formatting
        function formatPrettifiedResponse(data) {
            // Create a copy of the data without the _prettified flag
            const displayData = { ...data };
            delete displayData._prettified;

            // If the backend has provided HTML formatting, use it
            if (displayData._html_formatted) {
                const html = displayData._html_formatted;
                delete displayData._html_formatted;
                return html;
            }

            // Otherwise, use a simplified version that just stringifies the JSON
            // This is a fallback and should rarely be used since we've moved the formatting to the server
            return JSON.stringify(displayData, null, 2)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }


        const errorDetailsPretty = computed(() => {
            if (error.value && error.value.details) {
                if (typeof error.value.details === 'string') return error.value.details;
                return JSON.stringify(error.value.details, null, 2);
            }
            return '';
        });

        // --- Utility Methods ---
        function getClientId(specificClientId) {
            return specificClientId || config.value.clientId;
        }

        function validateClientId(clientId, errorMessage = 'Target Node ID (Client ID) is required.') {
            if (!clientId) {
                showErrorInModal(errorMessage);
                return false;
            }
            return true;
        }

        function parseJsonPayload(jsonString, errorPrefix) {
            try {
                // Allow empty string to be parsed as null for optional payloads
                if (jsonString.trim() === "") return null;
                return JSON.parse(jsonString);
            } catch (e) {
                showErrorInModal(`Invalid JSON for ${errorPrefix}.`, e.message);
                return undefined; // Return undefined to signal parsing failure
            }
        }

        function buildRequestParams(paramSource, requiredFields = [], optionalFields = []) {
            const currentClientId = getClientId(paramSource.clientId);
            if (!validateClientId(currentClientId)) return null;

            const requestData = {clientId: currentClientId};

            for (const field of requiredFields) {
                if (!paramSource[field] && paramSource[field] !== 0) { // Allow 0 as a valid value
                    showErrorInModal(`${field.charAt(0).toUpperCase() + field.slice(1)} is required for this operation.`);
                    return null;
                }
                requestData[field] = paramSource[field];
            }

            for (const field of optionalFields) {
                if (paramSource[field] !== undefined && paramSource[field] !== '' && paramSource[field] !== null) {
                    requestData[field] = paramSource[field];
                }
            }
            return requestData;
        }


        // --- UI Methods ---
        function showErrorInModal(message, details = null) {
            error.value = {
                message: message,
                details: details ? (typeof details === 'string' ? details : JSON.stringify(details, null, 2)) : null
            };
            response.value = null;
            showResponseModal.value = true;
            console.error("Error:", message, details);
        }

        function closeResponseModal() {
            showResponseModal.value = false;
        }

        // --- API Communication ---
        async function executeProxyCall(laravelEndpointPath, requestData = {}) {
            loading.value = true;
            response.value = null;
            error.value = null;

            const payloadToLaravelProxy = {
                baseUrl: config.value.baseUrl,
                apiKey: config.value.apiKey,
                prettify: config.value.prettify,
                ...requestData
            };

            try {
                const res = await fetch(`/laravel-foundry-api${laravelEndpointPath}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payloadToLaravelProxy)
                });

                const data = await res.json();

                if (!res.ok || data.error || (data.status_code && data.status_code >= 400)) {
                    throw data;
                }
                response.value = data;
            } catch (err) {
                showErrorInModal(
                    err.message || (err.error ? `API Error: ${err.error}` : 'Request to proxy failed or API returned an error.'),
                    err
                );
            } finally {
                loading.value = false;
                if (response.value || error.value) {
                    showResponseModal.value = true;
                }
            }
        }

        async function callGetApiDocs() {
            await executeProxyCall('/get-api-docs');
        }

        async function callGetApiStatus() {
            await executeProxyCall('/get-api-status');
        }

        async function callGetClients() {
            await executeProxyCall('/get-clients');
        }

        async function callGetStructure() {
            const currentClientId = getClientId(params.value.getStructure.clientId);
            if (!validateClientId(currentClientId, 'Target Node ID is required for Get Structure.')) return;
            await executeProxyCall('/get-structure', {clientId: currentClientId});
        }

        async function callGetContentsByPath() {
            const requestData = buildRequestParams(params.value.getContentsByPath, ['path']);
            if (!requestData) return;
            await executeProxyCall('/get-contents-by-path', requestData);
        }

        async function callSearchEntities() {
            const requestData = buildRequestParams(params.value.searchEntities, ['query'], ['filter']);
            if (!requestData) return;
            await executeProxyCall('/search-entities', requestData);
        }

        async function callSelectEntities() {
            const currentClientId = getClientId(params.value.selectEntities.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.selectEntities.payload, 'Select Entities');
            if (payload === undefined) return;
            await executeProxyCall('/select-entities', {clientId: currentClientId, payload: payload});
        }

        async function callGetSelectedEntities() {
            const currentClientId = getClientId(params.value.getSelectedEntities.clientId);
            if (!validateClientId(currentClientId)) return;
            await executeProxyCall('/get-selected-entities', {clientId: currentClientId});
        }

        async function callGetEntity() {
            const requestData = buildRequestParams(params.value.getEntity, [], ['uuid', 'selected', 'actor']);
            if (!requestData) return;
            await executeProxyCall('/get-entity', requestData);
        }

        async function callCreateEntity() {
            const currentClientId = getClientId(params.value.createEntity.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.createEntity.payload, 'Create Entity');
            if (payload === undefined) return;
            await executeProxyCall('/create-entity', {clientId: currentClientId, payload: payload});
        }

        async function callUpdateEntity() {
            const currentClientId = getClientId(params.value.updateEntity.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.updateEntity.payload, 'Update Entity');
            if (payload === undefined) return;

            const requestData = {clientId: currentClientId, payload: payload};
            ['uuid', 'selected', 'actor'].forEach(field => {
                if (params.value.updateEntity[field] !== undefined && params.value.updateEntity[field] !== '') {
                    requestData[field] = params.value.updateEntity[field];
                }
            });
            await executeProxyCall('/update-entity', requestData);
        }

        async function callDeleteEntity() {
            const requestData = buildRequestParams(params.value.deleteEntity, [], ['uuid', 'selected']);
            if (!requestData) return;
            await executeProxyCall('/delete-entity', requestData);
        }

        async function callGetSheet() {
            const requestData = buildRequestParams(
                params.value.getSheet, [],
                ['uuid', 'selected', 'actor', 'format', 'scale', 'tab', 'darkMode']
            );
            if (!requestData) return;
            await executeProxyCall('/get-sheet', requestData);
        }

        async function callGetMacros() {
            const currentClientId = getClientId(params.value.getMacros.clientId);
            if (!validateClientId(currentClientId, 'Target Node ID is required for Get Macros.')) return;
            await executeProxyCall('/get-macros', {clientId: currentClientId});
        }

        async function callExecuteMacro() {
            const requestData = buildRequestParams(params.value.executeMacro, ['uuid']);
            if (!requestData) return;

            let args = parseJsonPayload(params.value.executeMacro.args, 'Macro arguments');
            if (args === undefined && params.value.executeMacro.args.trim() !== "") return;
            if (args === null && params.value.executeMacro.args.trim() === "") args = {};

            requestData.args = args;
            await executeProxyCall('/execute-macro', requestData);
        }

        async function callGetRolls() {
            const requestData = buildRequestParams(params.value.getRolls, [], ['limit']);
            if (!requestData) return;
            await executeProxyCall('/get-rolls', requestData);
        }

        async function callGetLastRoll() {
            const currentClientId = getClientId(params.value.getLastRoll.clientId);
            if (!validateClientId(currentClientId)) return;
            await executeProxyCall('/get-lastroll', {clientId: currentClientId});
        }

        async function callMakeRoll() {
            const currentClientId = getClientId(params.value.makeRoll.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.makeRoll.payload, 'Make Roll');
            if (payload === undefined) return;
            await executeProxyCall('/make-roll', {clientId: currentClientId, payload: payload});
        }

        async function callGetEncounters() {
            const currentClientId = getClientId(params.value.getEncounters.clientId);
            if (!validateClientId(currentClientId)) return;
            await executeProxyCall('/get-encounters', {clientId: currentClientId});
        }

        async function callStartEncounter() {
            const currentClientId = getClientId(params.value.startEncounter.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.startEncounter.payload, 'Start Encounter');
            if (payload === undefined) return;
            await executeProxyCall('/start-encounter', {clientId: currentClientId, payload: payload});
        }

        async function callNextTurnInEncounter() {
            const requestData = buildRequestParams(params.value.nextTurnInEncounter, [], ['encounterId']);
            if (!requestData) return;
            await executeProxyCall('/next-turn-in-encounter', requestData);
        }

        async function callNextRoundInEncounter() {
            const requestData = buildRequestParams(params.value.nextRoundInEncounter, [], ['encounterId']);
            if (!requestData) return;
            await executeProxyCall('/next-round-in-encounter', requestData);
        }

        async function callPreviousTurnInEncounter() {
            const requestData = buildRequestParams(params.value.previousTurnInEncounter, [], ['encounterId']);
            if (!requestData) return;
            await executeProxyCall('/previous-turn-in-encounter', requestData);
        }

        async function callPreviousRoundInEncounter() {
            const requestData = buildRequestParams(params.value.previousRoundInEncounter, [], ['encounterId']);
            if (!requestData) return;
            await executeProxyCall('/previous-round-in-encounter', requestData);
        }

        async function callEndEncounter() {
            const requestData = buildRequestParams(params.value.endEncounter, [], ['encounterId']);
            if (!requestData) return;
            await executeProxyCall('/end-encounter', requestData);
        }

        async function callAddToEncounter() {
            const currentClientId = getClientId(params.value.addToEncounter.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.addToEncounter.payload, 'Add to Encounter');
            if (payload === undefined) return;
            const requestData = {clientId: currentClientId, payload: payload};
            if (params.value.addToEncounter.encounterId) {
                requestData.encounterId = params.value.addToEncounter.encounterId;
            }
            await executeProxyCall('/add-to-encounter', requestData);
        }

        async function callRemoveFromEncounter() {
            const currentClientId = getClientId(params.value.removeFromEncounter.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.removeFromEncounter.payload, 'Remove from Encounter');
            if (payload === undefined) return;
            const requestData = {clientId: currentClientId, payload: payload};
            if (params.value.removeFromEncounter.encounterId) {
                requestData.encounterId = params.value.removeFromEncounter.encounterId;
            }
            await executeProxyCall('/remove-from-encounter', requestData);
        }

        async function callMarkEntityAsDefeated() {
            const requestData = buildRequestParams(params.value.markEntityAsDefeated, [], ['uuid', 'selected']);
            if (!requestData) return;
            await executeProxyCall('/mark-entity-as-defeated', requestData);
        }

        async function callDecreaseAttribute() {
            const requestData = buildRequestParams(params.value.decreaseAttribute, ['attribute', 'amount'], ['uuid', 'selected']);
            if (!requestData) return;
            requestData.amount = parseFloat(requestData.amount);
            if (isNaN(requestData.amount)) {
                showErrorInModal('Amount must be a valid number for Decrease Attribute.');
                return;
            }
            await executeProxyCall('/decrease-attribute', requestData);
        }

        async function callIncreaseAttribute() {
            const currentClientId = getClientId(params.value.increaseAttribute.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.increaseAttribute.payload, 'Increase Attribute');
            if (payload === undefined) return;
            if (!payload || typeof payload.attribute !== 'string' || typeof payload.amount !== 'number') {
                showErrorInModal('Payload for Increase Attribute must contain a string "attribute" and a numeric "amount".');
                return;
            }
            await executeProxyCall('/increase-attribute', {clientId: currentClientId, payload: payload});
        }

        async function callGiveItem() {
            const currentClientId = getClientId(params.value.giveItem.clientId);
            if (!validateClientId(currentClientId)) return;
            const payload = parseJsonPayload(params.value.giveItem.payload, 'Give Item');
            if (payload === undefined) return;
            if (!payload || typeof payload.itemUuid !== 'string') {
                showErrorInModal('Payload for Give Item must contain "itemUuid".');
                return;
            }
            await executeProxyCall('/give-item', {clientId: currentClientId, payload: payload});
        }

        // --- Sidebar Methods ---
        function toggleSidebar() {
            sidebarOpen.value = !sidebarOpen.value;
        }

        function getSubsectionClass(color) {
            return `sidebar-text-${color}`;
        }

        function scrollToElement(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                if (window.innerWidth < 768) {
                    sidebarOpen.value = false;
                }
                const yOffset = -20;
                const y = element.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({top: y, behavior: 'smooth'});
            }
        }

        function generateSidebarSections() {
            const sections = [];
            const userInterfaceDiv = document.getElementById('userInterface');
            if (!userInterfaceDiv) return;

            const sectionDivs = userInterfaceDiv.children;
            for (let sectionDiv of sectionDivs) {
                if (!sectionDiv.classList.contains('api-section')) continue;

                const h3 = sectionDiv.querySelector('h3');
                if (!h3) continue;

                if (!h3.id) {
                    h3.id = 'section-' + h3.textContent.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]+/g, '');
                }
                const sectionTitle = h3.textContent.trim();
                const sectionId = h3.id;
                const sectionColor = getHeadingColor(h3);
                const subsections = [];
                const h4Elements = sectionDiv.querySelectorAll('h4');

                h4Elements.forEach(h4 => {
                    if (!h4.id) {
                        h4.id = 'subsection-' + h4.textContent.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]+/g, '');
                    }
                    subsections.push({
                        title: h4.textContent.trim().split('(')[0].trim(),
                        id: h4.id,
                        color: getHeadingColor(h4) || sectionColor
                    });
                });
                sections.push({title: sectionTitle, id: sectionId, color: sectionColor, subsections: subsections});
            }
            sidebarSections.value = sections;
        }

        function getHeadingColor(headingElement) {
            const colorMappings = {
                'text-lime-300': 'lime', 'text-lime-400': 'lime',
                'text-yellow-400': 'yellow',
                'text-red-500': 'red',
                'text-purple-300': 'purple', 'text-purple-400': 'purple',
                'text-cyan-400': 'cyan',
                'text-orange-400': 'orange',
                'text-teal-400': 'teal'
            };
            for (const cls in colorMappings) {
                if (headingElement.classList.contains(cls)) return colorMappings[cls];
            }
            return 'cyan';
        }


        function updateActiveSection() {
            const scrollPosition = window.scrollY;
            let currentSectionId = null;
            let currentSubsectionId = null;

            for (const section of sidebarSections.value) {
                const sectionElement = document.getElementById(section.id);
                if (!sectionElement) continue;

                const sectionTop = sectionElement.offsetTop - 100;
                const sectionBottom = sectionTop + sectionElement.offsetHeight;

                if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                    currentSectionId = section.id;
                    for (const subsection of section.subsections) {
                        const subsectionElement = document.getElementById(subsection.id);
                        if (!subsectionElement) continue;
                        const subsectionTop = subsectionElement.offsetTop - 100;
                        const subsectionBottom = subsectionTop + subsectionElement.offsetHeight;
                        if (scrollPosition >= subsectionTop && scrollPosition < subsectionBottom) {
                            currentSubsectionId = subsection.id;
                            break;
                        }
                    }
                    break;
                }
            }
            activeSection.value = currentSectionId;
            activeSubsection.value = currentSubsectionId;
        }

        onMounted(() => {
            nextTick(() => {
                generateSidebarSections();
                updateActiveSection();
                window.addEventListener('scroll', updateActiveSection);
                sidebarOpen.value = window.innerWidth >= 768;
                window.addEventListener('resize', () => {
                    sidebarOpen.value = window.innerWidth >= 768;
                });
            });
        });

        watch(() => config.value.clientId, (newVal) => {
            if (newVal) {
                Object.keys(params.value).forEach(key => {
                    if (params.value[key] && typeof params.value[key] === 'object' && 'clientId' in params.value[key]) {
                        params.value[key].clientId = newVal;
                    }
                });
                clientIdReadOnly.value = true;
            } else {
                clientIdReadOnly.value = false;
            }
        }, {deep: false});

        return {
            config, params, response, error, loading, showResponseModal,
            clientIdReadOnly, sidebarOpen, sidebarSections, activeSection, activeSubsection,
            responsePretty, errorDetailsPretty,
            closeResponseModal, toggleSidebar, getSubsectionClass, scrollToElement,
            callGetApiDocs, callGetApiStatus, callGetClients,
            callGetStructure, callGetContentsByPath, callSearchEntities,
            callSelectEntities, callGetSelectedEntities, callGetEntity,
            callCreateEntity, callUpdateEntity, callDeleteEntity,
            callGetSheet, callGetMacros, callExecuteMacro,
            callGetRolls, callGetLastRoll, callMakeRoll,
            callGetEncounters, callStartEncounter,
            callNextTurnInEncounter, callNextRoundInEncounter,
            callPreviousTurnInEncounter, callPreviousRoundInEncounter,
            callEndEncounter, callAddToEncounter, callRemoveFromEncounter,
            callMarkEntityAsDefeated, callDecreaseAttribute, callIncreaseAttribute, callGiveItem
        };
    }
}).mount('#foundryApiTesterApp');
