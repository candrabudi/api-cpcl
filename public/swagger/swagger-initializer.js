window.onload = function () {
  window.ui = SwaggerUIBundle({
    url: "/docs/openapi.yaml?v=" + Date.now(),
    dom_id: '#swagger-ui',
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    layout: "StandaloneLayout",

    // 🔥 AUTO INJECT TOKEN KE SETIAP REQUEST
    requestInterceptor: (req) => {
      const token = localStorage.getItem('swagger_jwt_token');

      if (token) {
        req.headers = req.headers || {};
        req.headers['Authorization'] = `Bearer ${token}`;
      }

      return req;
    },

    // 🔥 AUTO SAVE TOKEN SETELAH LOGIN
    responseInterceptor: (res) => {
      try {
        const url = res?.url || '';
        const body = res?.body;

        // DETEKSI LOGIN ENDPOINT
        if (
          url.includes('/auth/login') &&
          body &&
          body.data &&
          body.data.access_token
        ) {
          localStorage.setItem(
            'swagger_jwt_token',
            body.data.access_token
          );
          console.log('JWT token saved to localStorage');
        }
      } catch (e) {
        console.warn('Failed to parse login response', e);
      }

      return res;
    }
  });
};
