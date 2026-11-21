/*
 * Feld "Position auf Karte" in den Backend-Masken des KulturNetz-Bundles.
 *
 * Jedes Element mit data-kulturnetz-map (JSON-Konfiguration aus
 * MapFieldListener) wird zur Leaflet-Karte:
 *  - Mittelpunkt und Zoom aus der gespeicherten Position, sonst Standardansicht,
 *  - Klick in die Karte setzt den Marker und schreibt Breite/Länge in die Felder,
 *  - die Schaltfläche "Adresse auf Karte bestimmen" sucht die Adresse aus den
 *    konfigurierten Feldern bei Nominatim (OpenStreetMap) und setzt den Marker.
 *
 * Leaflet kommt aus dem Theme-Build (lokal, keine Einbindung von unpkg.com) und
 * wird bei Bedarf nachgeladen. Das Backend lädt Seiten per Turbo; das Skript
 * wird bei jeder Darstellung erneut ausgeführt und richtet nur neue Karten ein.
 */
(() => {
    const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    const GEOCODER_URL = 'https://nominatim.openstreetmap.org/search?format=json&q=';

    const loadLeaflet = (src) => {
        if (window.L) {
            return Promise.resolve(window.L);
        }

        window.kulturnetzLeafletLoading ??= new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.addEventListener('load', () => resolve(window.L));
            script.addEventListener('error', () => {
                window.kulturnetzLeafletLoading = null;
                reject(new Error(`Leaflet konnte nicht geladen werden: ${src}`));
            });
            document.head.appendChild(script);
        });

        return window.kulturnetzLeafletLoading;
    };

    const createMap = (L, element, config) => {
        const hasPosition = config.hasPosition;
        const center = hasPosition ? [config.lat, config.lon] : config.defaultCenter;
        const map = L.map(element).setView(center, hasPosition ? config.positionZoom : config.defaultZoom);
        element.kulturnetzLeafletMap = map;
        let marker = hasPosition ? L.marker([config.lat, config.lon]).addTo(map) : null;

        L.tileLayer(TILE_URL, { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

        const placeMarker = (latLng) => {
            if (marker) {
                marker.setLatLng(latLng);
            } else {
                marker = L.marker(latLng).addTo(map);
            }
        };

        map.on('click', (event) => {
            const latField = document.getElementById(config.latField);
            const lonField = document.getElementById(config.lonField);

            if (latField && lonField) {
                latField.value = event.latlng.lat;
                lonField.value = event.latlng.lng;
            }

            placeMarker(event.latlng);
            map.panTo(event.latlng);
        });

        const button = document.getElementById(config.locateButton);

        if (!button) {
            return;
        }

        // Die Schaltfläche bleibt wie bisher ein Absende-Button des Formulars;
        // ein Klick (auch die Eingabetaste in einem Textfeld) sendet nicht ab.
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const fields = config.addressFields.map((id) => document.getElementById(id));

            if (fields.some((field) => !field)) {
                return;
            }

            const address = `${fields[0].value}, ${fields[1].value} ${fields[2].value}`;
            const request = new XMLHttpRequest();

            request.onreadystatechange = () => {
                if (request.readyState !== XMLHttpRequest.DONE) {
                    return;
                }

                if (request.status === 200) {
                    const response = JSON.parse(request.responseText);

                    if (response.length > 0) {
                        const latLng = [parseFloat(response[0].lat), parseFloat(response[0].lon)];
                        const latField = document.getElementById(config.latField);
                        const lonField = document.getElementById(config.lonField);

                        placeMarker(latLng);
                        map.flyTo(latLng, 15);

                        if (latField && lonField) {
                            latField.value = response[0].lat;
                            lonField.value = response[0].lon;
                        }
                    }
                } else if (request.status === 400) {
                    alert('There was an error 400');
                } else {
                    alert('something else other than 200 was returned');
                }
            };

            request.open('GET', GEOCODER_URL + address, true);
            request.send();
        });
    };

    const render = () => {
        document.querySelectorAll('[data-kulturnetz-map]').forEach((element) => {
            if (element.dataset.kulturnetzMapReady) {
                return;
            }

            element.dataset.kulturnetzMapReady = '1';

            const config = JSON.parse(element.dataset.kulturnetzMap);

            loadLeaflet(config.leaflet).then((L) => createMap(L, element, config)).catch((error) => {
                element.dataset.kulturnetzMapReady = '';
                console.error(error);
            });
        });
    };

    render();

    if (!window.kulturnetzBackendMapBound) {
        window.kulturnetzBackendMapBound = true;
        document.addEventListener('turbo:render', render);

        // Turbo speichert eine Kopie der Seite für "Zurück"; die Karte wird dort
        // ohne Leaflet-Instanz abgelegt und beim nächsten Rendern neu aufgebaut.
        document.addEventListener('turbo:before-cache', () => {
            document.querySelectorAll('[data-kulturnetz-map]').forEach((element) => {
                if (element.kulturnetzLeafletMap) {
                    element.kulturnetzLeafletMap.remove();
                    element.kulturnetzLeafletMap = null;
                }

                element.dataset.kulturnetzMapReady = '';
            });
        });
    }
})();
