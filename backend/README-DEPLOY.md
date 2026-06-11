# Delicias API

API Laravel usada por el frontend ubicado en la raíz del repositorio.

## Endpoint Google

```text
POST /api/auth/google
```

Body:

```json
{
  "id_token": "token-emitido-por-google"
}
```

El endpoint valida el token con Google, comprueba el `GOOGLE_CLIENT_ID` y solo
inicia sesión si el correo verificado pertenece a un usuario activo existente.

Consulta `../DEPLOY-CPANEL.md` para el despliegue completo.
