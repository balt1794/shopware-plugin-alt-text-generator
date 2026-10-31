const { ApiService } = Shopware.Classes;

/**
 * Talks to the plugin's own routes. The API key never reaches the browser: the
 * server reads the image, sends it to Alt Text Generator AI and returns the text.
 */
class AltTextGeneratorApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/alt-text-generator') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'altTextGeneratorApiService';
    }

    getSettings() {
        return this.httpClient
            .get(this.getApiBasePath('settings'), { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    updateSettings(settings) {
        return this.httpClient
            .put(this.getApiBasePath('settings'), settings, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getCredits() {
        return this.httpClient
            .get(this.getApiBasePath('credits'), { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getImages(filter) {
        return this.httpClient
            .get(this.getApiBasePath('images'), { params: { filter }, headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    generate(id, language) {
        return this.httpClient
            .post(this.getApiBasePath('generate'), { id, language }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    save(items) {
        return this.httpClient
            .post(this.getApiBasePath('save'), { items }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

Shopware.Service().register('altTextGeneratorApiService', () => {
    return new AltTextGeneratorApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
