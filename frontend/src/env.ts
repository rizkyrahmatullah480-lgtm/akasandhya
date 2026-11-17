import { defineEnvVars } from '@sveltejs/kit/env';

/**
 * Public environment variables for the Invitaku frontend.
 * Values can be overridden with a `.env` file in the `frontend` directory:
 *
 *   PUBLIC_API_URL=http://127.0.0.1:8000/api
 */
export const variables = defineEnvVars({
	PUBLIC_API_URL: {
		public: true,
		schema: (value: string | undefined) => value || 'http://127.0.0.1:8000/api'
	}
});
