window.onload = function () {
  const SECURITY_SCHEME = 'sanctum';
  const REFRESHED_TOKEN_HEADER = 'x-refreshed-token';
  const TOKEN_IN_BODY_PATHS = ['/auth/login', '/auth/refresh'];
  const LOGOUT_PATH = '/auth/logout';

  const authorize = (token) => {
    window.ui.authActions.authorize({
      [SECURITY_SCHEME]: {
        name: SECURITY_SCHEME,
        schema: { type: 'http', scheme: 'bearer' },
        value: token,
      },
    });
  };

  const tokenFrom = (response) => {
    const refreshedToken = response.headers[REFRESHED_TOKEN_HEADER];

    if (refreshedToken) {
      return refreshedToken;
    }

    const returnsTokenInBody = TOKEN_IN_BODY_PATHS.some((path) => response.url.endsWith(path));

    if (response.ok && returnsTokenInBody) {
      return response.body?.data?.token ?? null;
    }

    return null;
  };

  window.ui = SwaggerUIBundle({
    url: './openapi.json',
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
    plugins: [SwaggerUIBundle.plugins.DownloadUrl],
    layout: 'StandaloneLayout',
    queryConfigEnabled: false,
    persistAuthorization: true,
    displayRequestDuration: true,
    tryItOutEnabled: true,
    responseInterceptor: (response) => {
      if (response.ok && response.url.endsWith(LOGOUT_PATH)) {
        window.ui.authActions.logout([SECURITY_SCHEME]);

        return response;
      }

      const token = tokenFrom(response);

      if (token) {
        authorize(token);
      }

      return response;
    },
  });
};
