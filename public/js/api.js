// ── HELPERS HTTP ─────────────────────────────────────────────
// El token viaja en HttpOnly cookie — JS no lo lee ni lo maneja.
// credentials: 'include' hace que el navegador envíe la cookie automáticamente.

function getToken() {
  // Ya no se usa localStorage para el token.
  // Devuelve true si hay un rol guardado (indica sesión activa).
  return !!localStorage.getItem('cl_rol');
}

async function apiFetch(method, path, body = null) {
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept':       'application/json',
    },
    credentials: 'include', // envía la HttpOnly cookie automáticamente
  };

  if (body) opts.body = JSON.stringify(body);

  const res  = await fetch(API + path, opts);
  const data = await res.json().catch(() => ({}));

  if (res.status === 401) {
    // Sesión expirada o inválida
    localStorage.removeItem('cl_rol');
    window.location.href = BASE_URL + '/login.html';
    return;
  }

  if (!res.ok) throw { status: res.status, data };
  return data;
}