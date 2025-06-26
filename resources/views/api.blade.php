{{-- Save as resources/views/foundry-api-tester.blade.php --}}
@extends('welcome') {{-- Ensure your layout.app includes TailwindCSS and a CSRF token meta tag --}}

@section('content')
    <div id="foundryApiTesterApp" class="container mx-auto mr-0 ml-0 md:ml-[24%] md:mr-[-5%] md:pr-4 p-4 font-sans bg-gray-900 min-h-screen text-gray-200 flex">
        <div class="cyber-sidebar fixed left-0 top-0 h-full w-64 bg-gray-800 bg-opacity-90 border-r-2 border-cyan-500 shadow-lg overflow-y-auto z-30 transform transition-transform duration-300 ease-in-out" :class="{'translate-x-0': sidebarOpen, '-translate-x-full md:translate-x-0': !sidebarOpen}">
            <div class="p-4 border-b border-cyan-500 flex justify-between items-center sticky top-0 bg-gray-800 bg-opacity-90 z-10">
                <h2 class="text-xl font-semibold text-cyan-400 glow-cyan">Navigation</h2>
                <button @click="toggleSidebar" class="text-gray-400 hover:text-cyan-400 transition-colors md:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <div v-for="(section, index) in sidebarSections" :key="section.id" class="mb-4">
                    <div @click="scrollToElement(section.id)"
                         class="text-lg font-medium text-cyan-300 hover:text-cyan-400 cursor-pointer mb-2 transition-colors duration-200 flex items-center"
                         :class="{'sidebar-section-active': activeSection === section.id, ['sidebar-text-' + section.color]: activeSection === section.id }">
                        <span class="mr-2">■</span>
                        <span>@{{ section.title }}</span>
                    </div>
                    <div v-if="section.subsections.length > 0" class="ml-4 border-l-2 border-gray-700 pl-2">
                        <div v-for="(subsection, subIndex) in section.subsections" :key="subsection.id"
                             @click="scrollToElement(subsection.id)"
                             class="text-sm py-1 hover:text-cyan-400 cursor-pointer transition-colors duration-200"
                             :class="[
                                getSubsectionClass(subsection.color),
                                {'sidebar-subsection-active': activeSubsection === subsection.id}
                             ]">
                            <span class="mr-1">▶</span>
                            <span>@{{ subsection.title }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="fixed top-4 left-4 z-40 md:hidden">
            <button @click="toggleSidebar" class="bg-gray-800 text-cyan-400 p-2 rounded-md border border-cyan-500 shadow-lg hover:bg-gray-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <div class="w-full transition-all duration-300 ease-in-out">
            <div class="max-w-6xl mx-auto">
                <h1 class="text-4xl font-bold mb-8 text-cyan-400 glow-cyan text-center tracking-wider pt-4">Foundry VTT API Conduit</h1>

                <div class="mb-10 p-6 border border-purple-500 rounded-lg shadow-xl bg-gray-800 bg-opacity-80 backdrop-blur-md api-section" id="config-section">
                    <h2 class="text-3xl font-semibold mb-6 text-pink-500 glow-pink tracking-wide">SYSTEM INTERFACE CONFIG</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="baseUrl" class="block text-sm font-medium text-purple-400 mb-1">Relay Endpoint (Base URL):</label>
                            <input type="text" v-model="config.baseUrl" id="baseUrl" class="cyber-input-field mt-1" autocomplete="off">
                        </div>
                        <div>
                            <label for="apiKey" class="block text-sm font-medium text-purple-400 mb-1">Auth Key (x-api-key):</label>
                            <input type="password" v-model="config.apiKey" id="apiKey" class="cyber-input-field mt-1" autocomplete="off" placeholder="Enter Secure API Key">
                        </div>
                        <div>
                            <label for="clientId" class="block text-sm font-medium text-purple-400 mb-1">Target Node ID (Client ID):</label>
                            <input type="text" v-model="config.clientId" id="clientId" class="cyber-input-field mt-1" autocomplete="off" placeholder="Default Connection Node">
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <input type="checkbox" v-model="config.prettify" id="prettify" class="form-checkbox h-5 w-5 text-pink-500 rounded border-gray-600 bg-gray-700 focus:ring-pink-500">
                        <label for="prettify" class="ml-2 text-sm font-medium text-purple-400">Enable Prettified Responses (with clickable IDs/UUIDs)</label>
                    </div>
                </div>

                <div class="space-y-8" id="userInterface">
                    <div class="p-6 border border-cyan-500 rounded-lg shadow-lg bg-gray-800 bg-opacity-70 api-section" id="core-utilities-section">
                        <h3 class="text-2xl font-semibold mb-4 text-cyan-400 glow-cyan tracking-wide">CORE UTILITIES</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button @click="callGetApiDocs" class="cyber-button bg-cyan-600 hover:bg-cyan-500 border-cyan-500">
                                <h4 id="get-api-docs">Access API Schematics<br>(`/get-api-docs`)</h4></button>
                            <button @click="callGetApiStatus" class="cyber-button bg-cyan-600 hover:bg-cyan-500 border-cyan-500">
                                <h4 id="get-api-status">System Status Ping<br>(`/get-api-status`)</h4></button>
                            <button @click="callGetClients" class="cyber-button bg-cyan-600 hover:bg-cyan-500 border-cyan-500">
                                <h4 id="get-clients">List Active Nodes<br>(`/get-clients`)</h4></button>
                        </div>
                    </div>

                    <div class="p-6 border border-lime-500 rounded-lg shadow-lg bg-gray-800 bg-opacity-70 api-section" id="data-grid-ops-section">
                        <h3 class="text-2xl font-semibold mb-4 text-lime-400 glow-lime tracking-wide">DATA GRID OPERATIONS</h3>
                        <div class="space-y-5">
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-lime-300 mb-2" id="get-structure">Fetch Directory Structure (`/get-structure`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.getStructure.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetStructure" class="cyber-button bg-lime-600 hover:bg-lime-500 border-lime-500 w-full mt-2">Retrieve Structure</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-lime-300 mb-2" id="get-contents-by-path">Scan Path Contents (`/get-contents-by-path`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.getContentsByPath.path" placeholder="Data Path (e.g., actors/SystemNodes)" class="cyber-input-field">
                                    <input type="text" v-model="params.getContentsByPath.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetContentsByPath" class="cyber-button bg-lime-600 hover:bg-lime-500 border-lime-500 w-full mt-2">Scan Path</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-lime-300 mb-2" id="search-entities">Query Data Entities (`/search-entities`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.searchEntities.query" placeholder="Search String (e.g., 'Protocol Omega')" class="cyber-input-field">
                                    <input type="text" v-model="params.searchEntities.filter" placeholder="Filter (e.g., type:program or id:774)" class="cyber-input-field">
                                    <input type="text" v-model="params.searchEntities.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callSearchEntities" class="cyber-button bg-lime-600 hover:bg-lime-500 border-lime-500 w-full mt-2">Initiate Search</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="select-entities">Target & Select Entities (`/select-entities`)</h4>
                                <div class="input-grid mb-2">
                                    <textarea v-model="params.selectEntities.payload" rows="3" placeholder="JSON Directive (see schematics)" class="cyber-textarea-field font-mono"></textarea>
                                    <input type="text" v-model="params.selectEntities.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callSelectEntities" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Execute Selection</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-lime-300 mb-2" id="get-selected-entities">Retrieve Selected Targets (`/get-selected-entities`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.getSelectedEntities.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetSelectedEntities" class="cyber-button bg-lime-600 hover:bg-lime-500 border-lime-500 w-full mt-2">Fetch Targets</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-lime-300 mb-2" id="get-entity">Inspect Entity Data (`/get-entity`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.getEntity.uuid" placeholder="Entity UID (optional)" class="cyber-input-field">
                                    <select v-model="params.getEntity.selected" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Selected? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <select v-model="params.getEntity.actor" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Actor Node? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <input type="text" v-model="params.getEntity.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetEntity" class="cyber-button bg-lime-600 hover:bg-lime-500 border-lime-500 w-full mt-2">Inspect</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="create-entity">Fabricate New Entity (`/create-entity`)</h4>
                                <div class="input-grid mb-2">
                                    <textarea v-model="params.createEntity.payload" rows="4" placeholder="JSON Blueprint (see schematics)" class="cyber-textarea-field font-mono"></textarea>
                                    <input type="text" v-model="params.createEntity.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callCreateEntity" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Fabricate</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="update-entity">Modify Existing Entity (`/update-entity`)</h4>
                                <div class="input-grid mb-2">
                                    <textarea v-model="params.updateEntity.payload" rows="3" placeholder="JSON Patch Data" class="cyber-textarea-field font-mono"></textarea>
                                    <input type="text" v-model="params.updateEntity.uuid" placeholder="Entity UID (optional if selected)" class="cyber-input-field">
                                    <select v-model="params.updateEntity.selected" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Selected? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <select v-model="params.updateEntity.actor" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Actor Node? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <input type="text" v-model="params.updateEntity.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callUpdateEntity" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Apply Modifications</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-500 mb-2" id="delete-entity">Decommission Entity (`/delete-entity`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.deleteEntity.uuid" placeholder="Entity UID (optional if selected)" class="cyber-input-field">
                                    <select v-model="params.deleteEntity.selected" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Selected? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <input type="text" v-model="params.deleteEntity.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callDeleteEntity" class="cyber-button bg-red-600 hover:bg-red-500 border-red-500 w-full mt-2">Decommission</button>
                            </div>

                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-lime-300 mb-2" id="get-sheet">Render Entity Sheet (`/get-sheet`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.getSheet.uuid" placeholder="Entity UID (optional)" class="cyber-input-field">
                                    <select v-model="params.getSheet.selected" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Selected? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <select v-model="params.getSheet.actor" class="cyber-select-field">
                                        <option value="" class="cyber-select-option">Is Actor Node? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                    </select>
                                    <select v-model="params.getSheet.format" class="cyber-select-field">
                                        <option value="json" class="cyber-select-option">Format: JSON Data</option> <option value="html" class="cyber-select-option">Format: HTML Render</option>
                                    </select>
                                    <input type="number" step="0.1" v-model.number="params.getSheet.scale" placeholder="Render Scale (e.g., 1.0, for HTML)" class="cyber-input-field">
                                    <input type="number" v-model.number="params.getSheet.tab" placeholder="Active Tab Index (for HTML)" class="cyber-input-field">
                                    <select v-model="params.getSheet.darkMode" class="cyber-select-field">
                                        <option :value="null" class="cyber-select-option">Dark Mode? (Default)</option> <option :value="true" class="cyber-select-option">Engage Dark Mode</option> <option :value="false" class="cyber-select-option">Disengage Dark Mode</option>
                                    </select>
                                    <input type="text" v-model="params.getSheet.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetSheet" class="cyber-button bg-lime-600 hover:bg-lime-500 border-lime-500 w-full mt-2">Render Sheet</button>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border border-purple-500 rounded-lg shadow-lg bg-gray-800 bg-opacity-70 api-section" id="macros-section">
                        <h3 class="text-2xl font-semibold mb-4 text-purple-400 glow-purple tracking-wide">MACRO EXECUTION UNIT</h3>
                        <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                            <h4 class="font-medium text-purple-300 mb-2" id="get-macros">List Available Macros (`/get-macros`)</h4>
                            <div class="input-grid mb-2">
                                <input type="text" v-model="params.getMacros.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                            </div>
                            <button @click="callGetMacros" class="cyber-button bg-purple-600 hover:bg-purple-500 border-purple-500 w-full mt-2">Scan Macros</button>
                        </div>
                        <div class="p-4 mt-5 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                            <h4 class="font-medium text-purple-300 mb-2" id="execute-macro">Execute Macro Protocol (`/execute-macro`)</h4>
                            <div class="input-grid mb-2">
                                <input type="text" v-model="params.executeMacro.uuid" placeholder="Macro UID" class="cyber-input-field">
                                <textarea v-model="params.executeMacro.args" rows="2" placeholder="JSON Arguments (optional, e.g. {})" class="cyber-textarea-field font-mono"></textarea>
                                <input type="text" v-model="params.executeMacro.clientId" placeholder="Target Node ID " class="cyber-input-field" :readonly="clientIdReadOnly">
                            </div>
                            <button @click="callExecuteMacro" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Execute Protocol</button>
                        </div>
                    </div>

                    <div class="p-6 border border-orange-500 rounded-lg shadow-lg bg-gray-800 bg-opacity-70 api-section" id="rolls-section">
                        <h3 class="text-2xl font-semibold mb-4 text-orange-400 glow-orange tracking-wide">PROBABILITY ENGINE (Rolls)</h3>
                        <div class="space-y-5">
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-orange-300 mb-2" id="get-rolls">Retrieve Roll History (`/get-rolls`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="number" v-model.number="params.getRolls.limit" placeholder="Limit (max 20, default 20)" class="cyber-input-field">
                                    <input type="text" v-model="params.getRolls.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetRolls" class="cyber-button bg-orange-600 hover:bg-orange-500 border-orange-500 w-full mt-2">Fetch Rolls</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-orange-300 mb-2" id="get-last-roll">Get Last Roll (`/get-lastroll`)</h4>
                                <div class="input-grid mb-2">
                                    <input type="text" v-model="params.getLastRoll.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callGetLastRoll" class="cyber-button bg-orange-600 hover:bg-orange-500 border-orange-500 w-full mt-2">Fetch Last Roll</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="make-roll">Initiate New Roll (`/make-roll`)</h4>
                                <div class="input-grid mb-2">
                                    <textarea v-model="params.makeRoll.payload" rows="3" placeholder="JSON Roll Directive (see schematics)" class="cyber-textarea-field font-mono"></textarea>
                                    <input type="text" v-model="params.makeRoll.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                </div>
                                <button @click="callMakeRoll" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Execute Roll</button>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border border-red-600 rounded-lg shadow-lg bg-gray-800 bg-opacity-70 api-section" id="encounters-section">
                        <h3 class="text-2xl font-semibold mb-4 text-red-500 glow-red tracking-wide">COMBAT MATRIX (Encounters)</h3>
                        <div class="space-y-5">
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-400 mb-2" id="get-encounters">Scan Active Encounters (`/get-encounters`)</h4>
                                <input type="text" v-model="params.getEncounters.clientId" placeholder="Target Node ID" class="cyber-input-field mb-2" :readonly="clientIdReadOnly">
                                <button @click="callGetEncounters" class="cyber-button bg-red-500 hover:bg-red-400 border-red-500 w-full mt-2">Scan Encounters</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="start-encounter">Initialize Encounter (`/start-encounter`)</h4>
                                <textarea v-model="params.startEncounter.payload" rows="3" placeholder="JSON Encounter Payload" class="cyber-textarea-field font-mono mb-2"></textarea>
                                <input type="text" v-model="params.startEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callStartEncounter" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Initialize</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-400 mb-2" id="next-turn">Advance Turn (`/next-turn`)</h4>
                                <input type="text" v-model="params.nextTurnInEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.nextTurnInEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callNextTurnInEncounter" class="cyber-button bg-red-500 hover:bg-red-400 border-red-500 w-full mt-2">Next Turn</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-400 mb-2" id="next-round">Advance Round (`/next-round`)</h4>
                                <input type="text" v-model="params.nextRoundInEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.nextRoundInEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callNextRoundInEncounter" class="cyber-button bg-red-500 hover:bg-red-400 border-red-500 w-full mt-2">Next Round</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-400 mb-2" id="prev-turn">Previous Turn (`/last-turn`)</h4>
                                <input type="text" v-model="params.previousTurnInEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.previousTurnInEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callPreviousTurnInEncounter" class="cyber-button bg-red-500 hover:bg-red-400 border-red-500 w-full mt-2">Previous Turn</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-400 mb-2" id="prev-round">Previous Round (`/last-round`)</h4>
                                <input type="text" v-model="params.previousRoundInEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.previousRoundInEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callPreviousRoundInEncounter" class="cyber-button bg-red-500 hover:bg-red-400 border-red-500 w-full mt-2">Previous Round</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="end-encounter">Terminate Encounter (`/end-encounter`)</h4>
                                <input type="text" v-model="params.endEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.endEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callEndEncounter" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Terminate</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="add-to-encounter">Add Combatants (`/add-to-encounter`)</h4>
                                <textarea v-model="params.addToEncounter.payload" rows="3" placeholder="JSON Combatant Payload" class="cyber-textarea-field font-mono mb-2"></textarea>
                                <input type="text" v-model="params.addToEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.addToEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callAddToEncounter" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Add Combatants</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="remove-from-encounter">Remove Combatants (`/remove-from-encounter`)</h4>
                                <textarea v-model="params.removeFromEncounter.payload" rows="3" placeholder="JSON Combatant Payload" class="cyber-textarea-field font-mono mb-2"></textarea>
                                <input type="text" v-model="params.removeFromEncounter.encounterId" placeholder="Encounter ID (optional)" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.removeFromEncounter.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callRemoveFromEncounter" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Remove Combatants</button>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border border-teal-500 rounded-lg shadow-lg bg-gray-800 bg-opacity-70 api-section" id="token-actions-section">
                        <h3 class="text-2xl font-semibold mb-4 text-teal-400 glow-teal tracking-wide">INSTANCE OPERATIONS</h3>
                        <div class="space-y-5">
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-red-400 mb-2" id="mark-defeated">Mark Defeated (`/kill`)</h4>
                                <input type="text" v-model="params.markEntityAsDefeated.uuid" placeholder="Entity UUID (optional)" class="cyber-input-field mb-2">
                                <select v-model="params.markEntityAsDefeated.selected" class="cyber-select-field mb-2">
                                    <option value="" class="cyber-select-option">Target Selected? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                </select>
                                <input type="text" v-model="params.markEntityAsDefeated.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callMarkEntityAsDefeated" class="cyber-button bg-red-500 hover:bg-red-400 border-red-500 w-full mt-2">Mark Defeated</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="decrease-attr">Decrease Attribute (`/decrease`)</h4>
                                <input type="text" v-model="params.decreaseAttribute.attribute" placeholder="Attribute Path (e.g., system.attributes.hp.value)" class="cyber-input-field mb-2">
                                <input type="number" v-model.number="params.decreaseAttribute.amount" placeholder="Amount to Decrease" class="cyber-input-field mb-2">
                                <input type="text" v-model="params.decreaseAttribute.uuid" placeholder="Entity UUID (optional)" class="cyber-input-field mb-2">
                                <select v-model="params.decreaseAttribute.selected" class="cyber-select-field mb-2">
                                    <option value="" class="cyber-select-option">Target Selected? (Default: no)</option> <option value="true" class="cyber-select-option">True</option> <option value="false" class="cyber-select-option">False</option>
                                </select>
                                <input type="text" v-model="params.decreaseAttribute.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callDecreaseAttribute" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Decrease</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="increase-attr">Increase Attribute (`/increase`)</h4>
                                <textarea v-model="params.increaseAttribute.payload" rows="3" placeholder="JSON Payload (attribute, amount, etc.)" class="cyber-textarea-field font-mono mb-2"></textarea>
                                <input type="text" v-model="params.increaseAttribute.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callIncreaseAttribute" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Increase</button>
                            </div>
                            <div class="p-4 border border-gray-700 rounded-md bg-gray-700 bg-opacity-50">
                                <h4 class="font-medium text-yellow-400 mb-2" id="give-item">Transfer Item (`/give`)</h4>
                                <textarea v-model="params.giveItem.payload" rows="3" placeholder="JSON Item Transfer Payload" class="cyber-textarea-field font-mono mb-2"></textarea>
                                <input type="text" v-model="params.giveItem.clientId" placeholder="Target Node ID" class="cyber-input-field" :readonly="clientIdReadOnly">
                                <button @click="callGiveItem" class="cyber-button bg-yellow-500 hover:bg-yellow-400 border-yellow-500 w-full mt-2">Transfer Item</button>
                            </div>
                        </div>
                    </div>


                </div> </div> </div> <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-40 p-4">
            <div class="text-yellow-400 text-2xl animate-pulse p-8 bg-gray-800 rounded-lg shadow-xl border border-yellow-500">
                Initializing Transmission... Stand By...
            </div>
        </div>

        <div v-if="showResponseModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300 ease-in-out" @click.self="closeResponseModal">
            <div class="cyber-modal-content w-full max-w-2xl max-h-[80vh] p-6 border border-pink-500 rounded-lg shadow-xl bg-black bg-opacity-90 text-gray-200 flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-semibold text-pink-400 glow-pink tracking-wide">DATA STREAM OUTPUT</h2>
                    <button @click="closeResponseModal" class="text-gray-400 hover:text-pink-400 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-grow overflow-y-auto mb-4 modal-scroll-content">
                    <!-- HTML content response -->
                    <div v-if="response && response.html_content" class="mt-2 p-4 bg-gray-800 border border-gray-700 text-gray-700 rounded shadow-inner overflow-auto max-h-[calc(80vh-200px)]" v-html="response.html_content"></div>

                    <!-- JSON response - both prettified and non-prettified use the same enhanced styling -->
                    <div v-if="response && !response.html_content && !error" class="max-h-[calc(80vh-200px)] overflow-auto">
                        <div v-if="!response._prettified" v-html="responsePretty"></div>
                        <div v-if="response._prettified" v-html="responsePretty"></div>
                    </div>

                    <!-- Error response -->
                    <div v-if="error" class="text-red-300 bg-red-900 bg-opacity-50 border border-red-700 p-4 rounded-md shadow-inner max-h-[calc(80vh-200px)] overflow-auto">
                        <p class="font-semibold text-red-400 glow-red">!! SYSTEM ALERT: TRANSMISSION ERROR !!</p>
                        <p class="mt-1 text-red-300">Error Code: @{{ error.message }}</p>
                        <pre v-if="error.detailsPretty" class="whitespace-pre-wrap break-all text-xs mt-2 p-2 bg-black bg-opacity-50 border border-red-800 rounded max-h-40 overflow-auto font-mono">@{{ error.detailsPretty }}</pre>
                    </div>

                    <!-- No response -->
                    <div v-if="!response && !error && !loading && showResponseModal" class="text-gray-500 text-center py-4">
                        No Data Transmitted or System Idle.
                    </div>
                </div>
                <button @click="closeResponseModal" class="cyber-button bg-pink-600 hover:bg-pink-500 border-pink-500 w-full mt-auto">Close Transmission</button>
            </div>
        </div>
    </div>
    <style>
        /* Styles for clickable IDs/UUIDs in the response modal */
        .clickable-id {
            display: inline-block;
            background-color: rgba(219, 39, 119, 0.2); /* pink-600 with opacity */
            color: #f472b6; /* pink-400 */
            padding: 0.1rem 0.3rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 1px solid rgba(219, 39, 119, 0.3);
            margin: 0 0.1rem;
        }

        .clickable-id:hover {
            background-color: rgba(219, 39, 119, 0.4);
            color: #f9a8d4; /* pink-300 */
            border-color: rgba(219, 39, 119, 0.6);
        }

        .clickable-id .copy-icon {
            margin-left: 0.3rem;
            display: inline-block;
            vertical-align: middle;
            opacity: 0.7;
        }

        .clickable-id:hover .copy-icon {
            opacity: 1;
        }

        /* Add a subtle animation when clicked to indicate copying */
        .clickable-id:active {
            transform: scale(0.95);
            background-color: rgba(219, 39, 119, 0.5);
        }
    </style>
@endsection
