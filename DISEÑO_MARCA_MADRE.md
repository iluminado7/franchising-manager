# Marca Madre — Sistema de diseño GoHarv

Este documento define las reglas visuales que **todo software de GoHarv** tiene que cumplir: Business Partner, Academy, Canal Ético y cualquier producto que venga después.

La fuente de verdad es la web institucional (`goharv-web.pages.dev/design`). La implementación de referencia en un software es **GoHarv Academy**. Si algo de este documento choca con un estilo que ya existe en el proyecto, **gana este documento**.

> Para la IA que trabaje en este proyecto: no inventes colores, tipografías, radios ni sombras. Todo sale de los tokens de este archivo. Si un componente necesita algo que no está acá, se resuelve combinando tokens existentes; no se agregan valores sueltos.

---

## 1. Principios

1. **Sobrio y premium.** La marca es una consultora de negocios. Se ve seria, limpia y con aire. Nada de colores chillones, gradientes, emojis ni ilustraciones infantiles.
2. **El dorado es el acento, no el protagonista.** Se usa para marcar lo importante: la acción principal, el ítem activo, el borde de una card. Si todo es dorado, nada se destaca.
3. **Bordes rectos.** La marca no usa esquinas redondeadas. Radio `0` en botones, cards, inputs, tablas y modales. La única excepción son los avatares, que son círculos.
4. **Jerarquía tipográfica antes que color.** La importancia se marca con el tamaño, el peso y las etiquetas en mayúscula, no pintando cosas.
5. **Componentes que leen roles, no colores.** Ningún componente usa `#BFAE76` directo. Usan `var(--accent)`, `var(--label)`, etc. Así el modo oscuro y cualquier cambio de marca se resuelven en un solo lugar.

---

## 2. Paleta

### Colores de marca (los únicos permitidos)

| Nombre | Hex | Token |
|---|---|---|
| Dorado | `#BFAE76` | `--gh-gold` |
| Dorado oscuro | `#8A7538` | `--gh-gold-dark` |
| Negro | `#000000` | `--gh-black` |
| Blanco | `#FFFFFF` | `--gh-white` |
| Gris texto | `#555555` | `--gh-gray-text` |
| Gris suave | `#F5F5F5` | `--gh-gray-soft` |

### Colores de apoyo (solo para UI de software)

Los softwares necesitan bordes, estados y modo oscuro, que la web institucional no tiene. Se agregan **únicamente** estos:

| Uso | Claro | Oscuro |
|---|---|---|
| Borde | `#E2E2E2` | `#2A2A2A` |
| Fondo app (oscuro) | — | `#0B0B0B` |
| Superficie (oscuro) | — | `#141414` |
| Texto secundario (oscuro) | — | `#B5B5B5` |
| Éxito | `#2E6B47` | `#6FBF8E` |
| Error | `#A33A3A` | `#E07C7C` |
| Advertencia | `#8A6A1F` | `#D9B45A` |

Los colores de estado se usan **solo** en texto chico, bordes o íconos de estado, nunca como fondos grandes ni en badges pastel.

### Reglas de contraste (obligatorias)

| Combinación | Contraste | ¿Se puede? | Para qué |
|---|---|---|---|
| Dorado `#BFAE76` sobre blanco | 2.2:1 | **No como texto** | Solo rellenos, bordes, barras de progreso, íconos decorativos |
| Dorado oscuro `#8A7538` sobre blanco | 4.5:1 | Sí, al límite | Etiquetas en mayúscula semibold, links. **No** para párrafos (ahí va `--text-muted`) |
| Dorado oscuro `#8A7538` sobre gris suave | 4.1:1 | Solo etiquetas cortas | Eyebrow dentro de la banda de encabezado |
| Negro sobre dorado | 9.5:1 | Sí | Texto del botón primario |
| Blanco sobre dorado oscuro | 4.5:1 | Sí | Hover del botón primario |
| Dorado `#BFAE76` sobre `#141414` | 8.4:1 | Sí | Etiquetas y acentos en modo oscuro |
| Dorado oscuro sobre `#141414` | 4.1:1 | **No** | En oscuro se usa el dorado claro |
| Blanco sobre dorado | 2.2:1 | **No** | — |

**En modo oscuro, las etiquetas pasan de `gold-dark` a `gold`.** Es el error más común; se resuelve con el token `--label`.

---

## 3. Tipografía

| Fuente | Pesos cargados | Uso |
|---|---|---|
| **Montserrat** | 400, 500, 600, 700 | Títulos, etiquetas, botones, números grandes, navegación |
| **Roboto** | 400, 500, 700 | Texto corrido, descripciones, celdas de tabla, inputs |

No se usa ninguna otra fuente (ni Inter, ni Poppins, ni la del sistema).

### Escala

| Elemento | Fuente | Peso | Tamaño | Notas |
|---|---|---|---|---|
| H1 (título de página) | Montserrat | 700 | `clamp(2rem, 1.5rem + 2vw, 3rem)` | Interlineado 1.1 |
| H2 (título de sección) | Montserrat | 700 | `clamp(1.5rem, 1.25rem + 1vw, 2rem)` | |
| H3 (título de card) | Montserrat | 600 | `1.25rem` | |
| Número de métrica | Montserrat | 700 | `clamp(2rem, 1.6rem + 1.5vw, 2.75rem)` | Siempre en `--text`, nunca en dorado |
| Etiqueta (eyebrow) | Montserrat | 600 | `0.8rem` | MAYÚSCULAS, `letter-spacing: 0.14em`, color `--label` |
| Botón | Montserrat | 600 | `0.875rem` | MAYÚSCULAS, `letter-spacing: 0.08em` |
| Navegación | Montserrat | 500 | `1rem` | |
| Cuerpo | Roboto | 400 | `1rem` | Interlineado 1.6, color `--text-muted` para descripciones |
| Texto chico / meta | Roboto | 400 | `0.875rem` | Fechas, "Última actividad", ayudas |

---

## 4. Tokens CSS (copiar tal cual)

Este archivo se llama `goharv-tokens.css`. Se importa **antes** que cualquier otro CSS del proyecto.

```css
/* goharv-tokens.css — Marca Madre GoHarv. No editar por proyecto. */
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap');

:root {
  /* ── Paleta de marca ── */
  --gh-gold: #BFAE76;
  --gh-gold-dark: #8A7538;
  --gh-black: #000000;
  --gh-white: #FFFFFF;
  --gh-gray-text: #555555;
  --gh-gray-soft: #F5F5F5;

  /* ── Roles (lo que usan los componentes) ── */
  --bg-app: var(--gh-white);
  --bg-band: var(--gh-gray-soft);   /* franja del encabezado de página */
  --surface: var(--gh-white);       /* cards, tablas, modales */
  --text: var(--gh-black);
  --text-muted: var(--gh-gray-text);
  --label: var(--gh-gold-dark);     /* etiquetas en mayúscula, links */
  --accent: var(--gh-gold);         /* rellenos, bordes, barras, ítem activo */
  --accent-soft: rgba(191, 174, 118, 0.15); /* fondo del ítem activo del menú */
  --border: #E2E2E2;

  --success: #2E6B47;
  --error: #A33A3A;
  --warning: #8A6A1F;

  /* ── Tipografía ── */
  --font-display: 'Montserrat', sans-serif;
  --font-body: 'Roboto', sans-serif;
  --tracking-label: 0.14em;
  --tracking-button: 0.08em;

  /* ── Forma y espacio ── */
  --radius: 0;
  --space-1: 0.25rem;
  --space-2: 0.5rem;
  --space-3: 1rem;
  --space-4: 1.5rem;
  --space-5: 2rem;
  --space-6: 3rem;
  --container: 1280px;
  --sidebar-w: 280px;
  --sidebar-w-collapsed: 88px;
  --topbar-h: 92px;
}

[data-theme="dark"] {
  --bg-app: #0B0B0B;
  --bg-band: #141414;
  --surface: #141414;
  --text: var(--gh-white);
  --text-muted: #B5B5B5;
  --label: var(--gh-gold);          /* gold-dark da 4.1:1 sobre #141414; gold da 8.4:1 */
  --accent-soft: rgba(191, 174, 118, 0.12);
  --border: #2A2A2A;

  --success: #6FBF8E;
  --error: #E07C7C;
  --warning: #D9B45A;
}

/* ── Base ── */
*, *::before, *::after { box-sizing: border-box; }

body {
  margin: 0;
  background: var(--bg-app);
  color: var(--text);
  font-family: var(--font-body);
  font-size: 1rem;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}

h1, h2, h3 { font-family: var(--font-display); color: var(--text); margin: 0; }
h1 { font-weight: 700; font-size: clamp(2rem, 1.5rem + 2vw, 3rem); line-height: 1.1; }
h2 { font-weight: 700; font-size: clamp(1.5rem, 1.25rem + 1vw, 2rem); line-height: 1.2; }
h3 { font-weight: 600; font-size: 1.25rem; line-height: 1.3; }

a { color: var(--label); }

:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
```

---

## 5. Componentes

Las clases llevan el prefijo `gh-` para que no choquen con el CSS de cada proyecto.

### 5.1 Etiqueta (eyebrow)

Va arriba de cada H1 de página e indica el rol o la sección. **Siempre lleva la rayita dorada adelante**, en todas las vistas y en todos los roles.

```css
.gh-label {
  display: inline-flex; align-items: center; gap: var(--space-3);
  font-family: var(--font-display); font-weight: 600; font-size: 0.8rem;
  text-transform: uppercase; letter-spacing: var(--tracking-label);
  color: var(--label);
}
.gh-label--line::before {
  content: ''; width: 40px; height: 1px; background: var(--accent);
}
```

```html
<span class="gh-label gh-label--line">Superadmin</span>
<h1>Inicio</h1>
<p class="gh-page-desc">Resumen general del sistema.</p>
```

### 5.2 Botones

Hay solo **dos variantes**: primario y secundario. Más un "ghost" para acciones de tabla. No se crean otras.

```css
.gh-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2);
  font-family: var(--font-display); font-weight: 600; font-size: 0.875rem;
  text-transform: uppercase; letter-spacing: var(--tracking-button);
  padding: 1.1rem 2.5rem; border: 1px solid transparent; border-radius: var(--radius);
  cursor: pointer; text-decoration: none; transition: background .2s, color .2s, border-color .2s;
}
.gh-btn--primary   { background: var(--accent); color: var(--gh-black); }
.gh-btn--primary:hover { background: var(--gh-gold-dark); color: var(--gh-white); }

.gh-btn--secondary { background: transparent; color: var(--text); border-color: var(--text); }
.gh-btn--secondary:hover { background: var(--text); color: var(--bg-app); }

/* Acciones dentro de tablas o cards: más chico, sin relleno */
.gh-btn--ghost {
  padding: 0.6rem 1.2rem; font-size: 0.75rem;
  background: transparent; color: var(--text); border-color: var(--border);
}
.gh-btn--ghost:hover { border-color: var(--accent); }

.gh-btn:disabled { opacity: .4; cursor: not-allowed; }
```

- **Una sola acción primaria por pantalla.** El resto son secundarias o ghost.
- El texto de los botones va en **mayúscula** y es un verbo: "Guardar", "Ver manuales", "Contactanos".
- "Cerrar sesión" **no es un botón**: es un ítem del menú lateral, abajo de todo.

### 5.3 Card de métrica

```css
.gh-stat {
  background: var(--surface);
  border: 1px solid var(--border);
  border-top: 3px solid var(--accent);
  border-radius: var(--radius);
  padding: var(--space-5);
}
.gh-stat__value {
  font-family: var(--font-display); font-weight: 700;
  font-size: clamp(2rem, 1.6rem + 1.5vw, 2.75rem); line-height: 1;
  color: var(--text);                 /* NUNCA en dorado */
}
.gh-stat__value small { font-size: 0.5em; color: var(--label); font-weight: 600; } /* "6/8" */
.gh-stat__label { margin-top: var(--space-3); }  /* + clase .gh-label */
```

```html
<div class="gh-stat">
  <div class="gh-stat__value">17</div>
  <div class="gh-stat__label gh-label">Manuales publicados</div>
</div>
```

- **El número arriba, la etiqueta abajo.** Así es en el hero institucional y en el Academy.
- Todos los números del mismo color. No se pinta uno en dorado "para destacarlo".
- Grilla: `repeat(auto-fit, minmax(260px, 1fr))` con `gap: var(--space-4)`.

### 5.4 Card de contenido / tabla

```css
.gh-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.gh-card__header {
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--border);
}

.gh-table { width: 100%; border-collapse: collapse; }
.gh-table th {
  text-align: left; padding: var(--space-3) var(--space-5);
  font-family: var(--font-display); font-weight: 600; font-size: 0.75rem;
  text-transform: uppercase; letter-spacing: var(--tracking-label);
  color: var(--label); border-bottom: 1px solid var(--border);
}
.gh-table td {
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--border); color: var(--text);
}
.gh-table tr:last-child td { border-bottom: 0; }
.gh-table tr:hover td { background: var(--accent-soft); }
```

### 5.5 Estado (reemplaza los badges pastel)

Los estados se muestran como **texto en mayúscula con un punto de color**, no como pastillas redondeadas con fondo.

```css
.gh-status {
  display: inline-flex; align-items: center; gap: var(--space-2);
  font-family: var(--font-display); font-weight: 600; font-size: 0.75rem;
  text-transform: uppercase; letter-spacing: var(--tracking-label);
  color: var(--text-muted);
}
.gh-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.gh-status--ok    { color: var(--success); }
.gh-status--error { color: var(--error); }
.gh-status--warn  { color: var(--warning); }
.gh-status--draft { color: var(--text-muted); }
```

```html
<span class="gh-status gh-status--ok">Activa</span>
```

### 5.6 Barra de progreso

```css
.gh-progress { height: 6px; background: var(--border); border-radius: var(--radius); overflow: hidden; }
.gh-progress__bar { height: 100%; background: var(--accent); }
.gh-progress--done .gh-progress__bar { background: var(--gh-gold-dark); } /* 100% se distingue */
```

### 5.7 Formularios

```css
.gh-field { display: flex; flex-direction: column; gap: var(--space-2); }
.gh-field label {
  font-family: var(--font-display); font-weight: 600; font-size: 0.75rem;
  text-transform: uppercase; letter-spacing: var(--tracking-label); color: var(--label);
}
.gh-input, .gh-select, .gh-textarea {
  font-family: var(--font-body); font-size: 1rem; color: var(--text);
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 0.85rem 1rem;
}
.gh-input:focus, .gh-select:focus, .gh-textarea:focus {
  outline: none; border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent);
}
.gh-field__error { font-size: 0.875rem; color: var(--error); }
```

### 5.8 Avatar

El único elemento redondo de la interfaz.

```css
.gh-avatar {
  width: 52px; height: 52px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-weight: 600; font-size: 0.9rem; letter-spacing: 0.05em;
  color: var(--label); background: var(--accent-soft); border: 1px solid var(--accent);
}
```

---

## 6. Layout de la aplicación

```
┌──────────────┬──────────────────────────────────────────────┐
│              │  TOPBAR: logo · · · · · tema · avisos · avatar│  ← borde inferior 1px dorado
│   SIDEBAR    ├──────────────────────────────────────────────┤
│              │  BANDA (bg-band): eyebrow + H1 + descripción  │  ← borde inferior 1px --border
│  navegación  ├──────────────────────────────────────────────┤
│              │                                              │
│  ──────────  │  CONTENIDO (bg-app)                          │
│  usuario     │  métricas · cards · tablas                   │
│  salir       │                                              │
│              ├──────────────────────────────────────────────┤
│              │  FOOTER: GOHARV <PRODUCTO> · AÑO             │  ← borde superior 1px dorado
└──────────────┴──────────────────────────────────────────────┘
      ↑ borde derecho 1px dorado
```

### Reglas fijas

| Elemento | Regla |
|---|---|
| **Logo** | Siempre en el **topbar**, a la izquierda. No se mueve al colapsar la sidebar. Debajo del logotipo GoHarv va el nombre del producto (`ACADEMY`, `BUSINESS PARTNER`) en versalitas. |
| **Topbar** | Fondo `--surface`, borde inferior `1px solid var(--accent)`. A la derecha, en este orden: alternar tema (ícono luna/sol), notificaciones (campana) y avatar. |
| **Sidebar** | Fondo `--surface`, borde derecho `1px solid var(--accent)`. Expandida `280px`, colapsada `88px`, con botón hamburguesa arriba. |
| **Ítem activo** | Fondo `--accent-soft` + barra vertical de 3px en `--accent` a la izquierda. Texto en `--text`, no en dorado. |
| **Sidebar colapsada** | Cada ícono lleva `aria-label` y un tooltip con el nombre al pasar el mouse. |
| **Pie de sidebar** | Separado con un borde: avatar + nombre + rol (como `gh-label`) y debajo el ítem "Salir" con su ícono. |
| **Banda de encabezado** | Fondo `--bg-band`. Contiene eyebrow con línea + H1 + descripción corta en `--text-muted`. **Está en todas las páginas.** |
| **Contenido** | Fondo `--bg-app`. Padding `var(--space-6)` en desktop y `var(--space-4)` en mobile. |
| **Footer** | `GOHARV <PRODUCTO> · <AÑO>` como `gh-label`, borde superior dorado. **Está en todas las páginas.** |
| **Íconos** | Lucide (trazo de 1.5px, sin relleno), tamaño 22–24px, color `--text`. No se mezclan sets de íconos. |

### Responsive

- Revisar siempre en **1440px** y en **375px**.
- Por debajo de 1024px la sidebar se oculta y se abre como panel superpuesto desde la hamburguesa.
- Por debajo de 640px las grillas de métricas pasan a una columna y las tablas tienen scroll horizontal dentro de su card (la página nunca tiene scroll horizontal).

---

## 7. Modo oscuro

- Se activa con `data-theme="dark"` en `<html>`. Se guarda la preferencia del usuario en `localStorage` y, si no hay ninguna, se respeta `prefers-color-scheme`.
- **No se escribe CSS específico de modo oscuro en los componentes.** Si un componente se ve mal en oscuro, es porque está usando un color directo en vez de un rol. Se corrige el componente.
- El botón primario sigue siendo dorado con texto negro en ambos modos.

---

## 8. Lo que está prohibido

- ❌ `border-radius` distinto de 0 (salvo avatares y el punto de `.gh-status`).
- ❌ Sombras (`box-shadow`) decorativas. La separación se hace con bordes de 1px.
- ❌ Gradientes.
- ❌ Fuentes que no sean Montserrat o Roboto.
- ❌ Colores fuera de la paleta y de los colores de apoyo de la sección 2.
- ❌ Texto en dorado claro `#BFAE76` sobre fondo blanco.
- ❌ Números de métricas en dorado.
- ❌ Badges tipo pastilla con fondo pastel (verde, azul, rosa).
- ❌ Más de un botón primario en la misma vista.
- ❌ Emojis en la interfaz.
- ❌ Títulos de página en Roboto o en pesos menores a 700.
- ❌ Hex escritos a mano dentro de componentes: todo pasa por tokens.

---

## 9. Ajustes pendientes en Business Partner

Estado actual comparado con esta guía:

| # | Hoy | Tiene que quedar |
|---|---|---|
| 1 | Cards de métricas con esquinas redondeadas y sin acento | `.gh-stat`: radio 0, borde superior dorado de 3px |
| 2 | Etiqueta arriba y número abajo ("EMPRESAS ACTIVAS / 1") | Número arriba, etiqueta abajo |
| 3 | Algunos números en dorado (1, 7) y otros en negro | Todos en `--text` |
| 4 | Título "Inicio" en Roboto/peso medio | H1 Montserrat 700 con eyebrow "SUPERADMIN" y línea dorada |
| 5 | Sin banda de encabezado | Banda `--bg-band` con eyebrow + H1 + descripción |
| 6 | Badge "Activa" verde pastel redondeado | `.gh-status gh-status--ok` |
| 7 | Botón "Ver manuales" redondeado | `.gh-btn gh-btn--ghost` |
| 8 | "Cerrar sesión" como botón en el topbar | Ítem "Salir" al pie de la sidebar, con avatar, nombre y rol arriba |
| 9 | Nombre del usuario en el topbar | Solo el avatar en el topbar; nombre y rol en el pie de la sidebar |
| 10 | "Modo oscuro" como ítem de texto al pie de la sidebar | Ícono luna/sol en el topbar |
| 11 | Logo GoHarv sin nombre de producto | Logo + `BUSINESS PARTNER` debajo |
| 12 | Topbar y sidebar con bordes grises | Borde inferior del topbar y borde derecho de la sidebar en dorado |
| 13 | Ítem activo con texto dorado | Fondo `--accent-soft` + barra lateral dorada, texto en `--text` |
| 14 | Sin footer | `GOHARV BUSINESS PARTNER · 2026` |
| 15 | Encabezados de tabla en gris | `gh-label` en `--label` |

---

## 10. Checklist antes de dar por terminada una vista

- [ ] Usa solo tokens; no hay hex sueltos en el CSS de la vista
- [ ] Tiene banda con eyebrow (con línea) + H1 + descripción
- [ ] Tiene footer
- [ ] Ningún elemento tiene `border-radius` salvo avatares
- [ ] Hay como mucho un botón primario
- [ ] Ningún texto chico está en dorado claro sobre blanco
- [ ] Se ve bien en modo claro **y** en modo oscuro
- [ ] Se ve bien a 1440px **y** a 375px, sin scroll horizontal de página
- [ ] Los íconos de la sidebar colapsada tienen `aria-label` y tooltip
- [ ] El foco del teclado se ve (`:focus-visible` dorado)