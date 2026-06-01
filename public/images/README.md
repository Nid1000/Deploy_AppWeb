# Carpeta de imágenes del proyecto

Esta carpeta centraliza las imágenes utilizadas en el frontend. Al estar dentro de `public/`, todos los archivos aquí son servidos estáticamente y accesibles desde la ruta `/images/...`.

## Estructura

```
public/
└── images/
    ├── logos/          # Logotipos de la marca y variantes
    ├── icons/          # Íconos en SVG u otros formatos
    ├── backgrounds/    # Fondos, patrones y texturas
    ├── banners/        # Banners promocionales
    ├── hero/           # Imágenes de secciones hero/encabezados
    ├── sections/       # Imágenes específicas por sección/página
    ├── categories/     # Imágenes representativas de categorías
    ├── products/       # Imágenes de productos
    ├── avatars/        # Avatares/usuarios
    ├── placeholders/   # Imágenes de relleno/placeholders
    ├── illustrations/  # Ilustraciones y gráficos
    └── sprites/        # Sprites y hojas de sprites
```

Se han añadido archivos `.gitkeep` para que las carpetas se rastreen en control de versiones incluso cuando están vacías.

## Convenciones de nombres

- Utiliza `kebab-case` (minúsculas con guiones): `nombre-descriptivo-variant.ext`
- Incluye variante si aplica: `light`, `dark`, `es`, `en`, etc.
- Para imágenes raster, añade tamaño y densidad cuando corresponda:
  - `hero-home-1920x1080.webp`
  - `icon-buscar@2x.png`
- Formatos recomendados:
  - SVG para íconos y logos (vectorial): `logo-principal.svg`
  - WebP o AVIF para fotos; JPG como fallback: `producto-x.webp`

## Ejemplos de uso

Usando en Blade o HTML:

```html
<img src="/images/logos/logo.svg" alt="Logo Delicias" width="120" height="40">

<img
  src="/images/hero/hero-home-1920x1080.webp"
  alt="Hero de la pagina principal"
  width="1920"
  height="1080"
>
```

Usando en CSS como fondo:

```css
.hero {
  background-image: url('/images/backgrounds/pattern-light.svg');
  background-size: cover;
  background-position: center;
}
```

## Buenas prácticas

- Optimiza las imágenes antes de subirlas (peso y dimensiones adecuadas).
- Evita incrustar texto en imágenes si será traducido; usa capas de texto en el UI.
- Mantén consistencia en nombres y variantes.
- Elimina imágenes obsoletas para evitar acumulación.

## Notas

- Si necesitas almacenar fuentes originales (PSD/AI/figexport), considera añadir una carpeta en `src/design/` para trabajo de diseño y exportar aquí solo los recursos finales optimizados.
