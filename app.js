/**
 * =====================================================================
 * TACÓN DE ORO - LÓGICA DEL FRONTEND (app.js)
 * Conectado a php/api.php:
 * - Registro y Login con Código de Verificación por correo (Resend)
 * - Validación de correo real y contraseña segura (8+ chars, mayúscula, número, símbolo)
 * - Catálogo dinámico con la Colección Nike del PDF + filtros por preferencia
 * - Avisos de descuentos por temporada en tiempo real
 * - Perfil del cliente (pedidos activos, compras anteriores, todas las compras, guías, editar, borrar cuenta)
 * - Panel exclusivo de Administradores (agregar/editar/ocultar/eliminar productos, precios, descuentos, avisos y guías de envío)
 * =====================================================================
 */

// =====================================================================
// 1. CONFIGURACIÓN, MAPA DE COLORES Y ESTADO GLOBAL
// =====================================================================
const API_URL = 'php/api.php';
const COSTO_ENVIO = 150.00;

const MAPA_COLORES = {
  Blanco: '#ffffff',
  Negro: '#181512',
  Gris: '#9e9e9e',
  Beige: '#d6c7a9',
  Café: '#7b4b36',
  Dorado: '#c8a24a',
  Rojo: '#c93b3b',
  Rosa: '#eb5e9d',
  Morado: '#7a1c64',
  Verde: '#3e7c47',
  Azul: '#1f4eb5',
  Celeste: '#5bb8e8'
};

// Respaldo local en caso de abrirse sin conexión inicial
const ARTICULOS_RESPALDO = [
  {
    id: 1, nombre: 'Nike Air Max Excee', marca: 'Nike', categoria: 'Caballero', linea: 'Deportivo',
    descripcion: 'Inspirado en el icónico Air Max 90 con unidad de aire visible en tres ventanas, malla transpirable y diseño alargado.',
    tallas: [25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0], colores: ['Blanco', 'Negro', 'Beige', 'Café'],
    precioBase: 1839.00, descuentoPct: 0, precio: 1839.00, precioAnterior: null, stock: 18, esOferta: false, esNuevo: true, visible: true
  },
  {
    id: 2, nombre: "Nike Air More Uptempo '96", marca: 'Nike', categoria: 'Caballero', linea: 'Deportivo',
    descripcion: 'Clásico del básquetbol de los años 90 con amortiguación Air de largo completo, correas elásticas y gráfico AIR audaz.',
    tallas: [25.5, 26.0, 26.5, 27.0, 27.5, 28.0, 28.5], colores: ['Blanco', 'Negro', 'Rosa', 'Azul'],
    precioBase: 4099.00, descuentoPct: 0, precio: 4099.00, precioAnterior: null, stock: 10, esOferta: false, esNuevo: true, visible: true
  },
  {
    id: 3, nombre: 'Nike Pantheos Drot', marca: 'Nike', categoria: 'Caballero', linea: 'Casual',
    descripcion: 'Silueta retro de running de los 90 que combina gamuza sintética, malla ligera y entresuela de espuma suave.',
    tallas: [25.0, 25.5, 26.0, 26.5, 27.0, 27.5], colores: ['Blanco', 'Negro', 'Verde', 'Gris'],
    precioBase: 1499.00, descuentoPct: 0, precio: 1499.00, precioAnterior: null, stock: 16, esOferta: false, esNuevo: true, visible: true
  },
  {
    id: 4, nombre: "Nike Air Force 1 '07 Triple White", marca: 'Nike', categoria: 'Caballero', linea: 'Casual',
    descripcion: 'Leyenda del calzado urbano con revestimientos cosidos duraderos, amortiguación Nike Air y cuello acolchado.',
    tallas: [24.0, 24.5, 25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0], colores: ['Blanco', 'Gris', 'Negro', 'Azul', 'Dorado'],
    precioBase: 2499.00, descuentoPct: 0, precio: 2499.00, precioAnterior: null, stock: 24, esOferta: false, esNuevo: true, visible: true
  },
  {
    id: 5, nombre: 'Nike Flex Trainer 7', marca: 'Nike', categoria: 'Dama', linea: 'Deportivo',
    descripcion: 'Tenis de entrenamiento ligero con malla transpirable, cables de soporte en el mediopié y suela flexible.',
    tallas: [22.5, 23.0, 23.5, 24.0, 24.5, 25.0, 25.5], colores: ['Blanco', 'Negro', 'Morado', 'Celeste'],
    precioBase: 1490.00, descuentoPct: 0, precio: 1490.00, precioAnterior: null, stock: 20, esOferta: false, esNuevo: false, visible: true
  },
  {
    id: 6, nombre: "Nike Blazer Mid '77 Vintage", marca: 'Nike', categoria: 'Caballero', linea: 'Casual',
    descripcion: 'Diseño clásico de cancha de los años 70 en corte medio con acabado retro y suela tipo espiga.',
    tallas: [24.0, 24.5, 25.0, 25.5, 26.0, 26.5, 27.0, 27.5], colores: ['Blanco', 'Negro', 'Verde', 'Dorado'],
    precioBase: 2399.00, descuentoPct: 0, precio: 2399.00, precioAnterior: null, stock: 15, esOferta: false, esNuevo: true, visible: true
  },
  {
    id: 7, nombre: 'Nike Air Max Sequent 4', marca: 'Nike', categoria: 'Caballero', linea: 'Deportivo',
    descripcion: 'Tenis de running con parte superior tejida adaptable y unidad Max Air en forma de U.',
    tallas: [25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0], colores: ['Gris', 'Negro', 'Rojo', 'Azul'],
    precioBase: 1199.00, descuentoPct: 25, precio: 899.00, precioAnterior: 1199.00, stock: 22, esOferta: true, esNuevo: false, visible: true
  },
  {
    id: 8, nombre: 'Nike Renew Serenity Run', marca: 'Nike', categoria: 'Dama', linea: 'Deportivo',
    descripcion: 'Diseñado para corredoras que buscan espuma ultrasuave Nike Renew y confort prolongado.',
    tallas: [22.5, 23.0, 23.5, 24.0, 24.5, 25.0, 25.5], colores: ['Blanco', 'Negro', 'Rojo', 'Rosa'],
    precioBase: 1899.00, descuentoPct: 0, precio: 1899.00, precioAnterior: null, stock: 14, esOferta: false, esNuevo: true, visible: true
  },
  {
    id: 9, nombre: 'Nike Kyrie Flytrap IV', marca: 'Nike', categoria: 'Caballero', linea: 'Deportivo',
    descripcion: 'Calzado de básquetbol ligero con bordes de tracción lateral y unidad Zoom Air en el antepié.',
    tallas: [25.5, 26.0, 26.5, 27.0, 27.5, 28.0, 28.5], colores: ['Blanco', 'Negro', 'Rojo', 'Azul'],
    precioBase: 2399.00, descuentoPct: 0, precio: 2399.00, precioAnterior: null, stock: 12, esOferta: false, esNuevo: true, visible: true
  }
];

let catalogoTienda = [...ARTICULOS_RESPALDO];
let catalogoAdmin = [];
let usuariosAdmin = [];
let avisosActivos = [];

let usuarioActual = null;
let correoPendienteLogin = '';
let correoPendienteRegistro = '';
let articuloSeleccionado = null;
let carrito = [];

const filtros = {
  busqueda: '',
  categoria: 'todas',
  marca: 'todas',
  linea: 'todos',
  talla: null,
  color: null,
  precioMaximo: 5000
};

// =====================================================================
// 2. INICIALIZACIÓN
// =====================================================================
document.addEventListener('DOMContentLoaded', async () => {
  crearFiltrosTallas();
  crearFiltrosColores();
  enlazarEventos();
  await cargarCatalogoDesdeServidor();
  await verificarSesionActiva();
});

function enlazarEventos() {
  document.getElementById('btnAbrirAuth')?.addEventListener('click', () => abrirModalAuth('login'));
  document.getElementById('btnCerrarSesion')?.addEventListener('click', cerrarSesion);
  document.getElementById('btnAbrirCarrito')?.addEventListener('click', abrirCarrito);
  document.getElementById('btnLimpiarFiltros')?.addEventListener('click', limpiarTodosLosFiltros);

  document.getElementById('inputBuscar')?.addEventListener('input', (e) => {
    filtros.busqueda = e.target.value.toLowerCase().trim();
    renderizarCatalogo();
  });

  document.getElementById('filtroMarca')?.addEventListener('change', (e) => {
    filtros.marca = e.target.value;
    renderizarCatalogo();
  });

  document.getElementById('filtroLinea')?.addEventListener('change', (e) => {
    filtros.linea = e.target.value;
    renderizarCatalogo();
  });

  const rango = document.getElementById('rangoPrecio');
  const textoPrecio = document.getElementById('textoPrecioMaximo');
  rango?.addEventListener('input', (e) => {
    filtros.precioMaximo = parseFloat(e.target.value);
    if (textoPrecio) textoPrecio.textContent = `$${filtros.precioMaximo.toLocaleString()}`;
    renderizarCatalogo();
  });

  document.getElementById('regContrasena')?.addEventListener('input', (e) => {
    actualizarChecklistContrasena(e.target.value, 'regPasswordChecklist');
  });

  document.getElementById('perfilPassNueva')?.addEventListener('input', (e) => {
    actualizarChecklistContrasena(e.target.value, 'perfilPasswordChecklist');
  });

  document.getElementById('adminUserPass')?.addEventListener('input', (e) => {
    actualizarChecklistContrasena(e.target.value, 'adminUserPasswordChecklist');
  });

  window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
      e.target.classList.remove('active');
    }
    if (e.target.classList.contains('drawer-backdrop')) {
      e.target.classList.remove('active');
    }
  });
}

// =====================================================================
// 3. COMUNICACIÓN CON LA API PHP Y CARGA DE CATÁLOGO
// =====================================================================
async function llamarAPI(accion, metodo = 'GET', datos = null) {
  const opciones = {
    method: metodo,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin'
  };

  if (datos && metodo !== 'GET') {
    opciones.body = JSON.stringify(datos);
  }

  try {
    const respuesta = await fetch(`${API_URL}?accion=${encodeURIComponent(accion)}`, opciones);
    const texto = await respuesta.text();
    try {
      return JSON.parse(texto);
    } catch {
      return {
        ok: false,
        mensaje: 'El servidor PHP no devolvió JSON válido. Abre el proyecto desde XAMPP (http://localhost/...).'
      };
    }
  } catch {
    return {
      ok: false,
      mensaje: 'No se pudo conectar con php/api.php. Ejecuta el sitio desde XAMPP.'
    };
  }
}

async function cargarCatalogoDesdeServidor() {
  const res = await llamarAPI('catalogo_publico', 'GET');
  if (res.ok && Array.isArray(res.articulos)) {
    catalogoTienda = res.articulos;
    avisosActivos = Array.isArray(res.avisos) ? res.avisos : [];
  }
  renderizarAvisosTemporada();
  renderizarSeccionesInicio();
}

function renderizarAvisosTemporada() {
  const seccion = document.getElementById('seccionAvisosTemporada');
  const contenedor = document.getElementById('contenedorAvisosTemporada');
  if (!seccion || !contenedor) return;

  if (!avisosActivos || avisosActivos.length === 0) {
    seccion.style.display = 'none';
    contenedor.innerHTML = '';
    return;
  }

  seccion.style.display = 'block';
  contenedor.innerHTML = avisosActivos.map(av => `
    <div class="seasonal-banner-card">
      <div class="seasonal-banner-copy">
        <strong><i class="fa-solid fa-bullhorn"></i> ${av.titulo}</strong>
        <span>${av.mensaje}</span>
      </div>
      ${av.etiqueta_descuento ? `<span class="seasonal-badge">${av.etiqueta_descuento}</span>` : ''}
    </div>
  `).join('');
}

// =====================================================================
// 4. AUTENTICACIÓN Y SEGURIDAD DE CONTRASEÑA
// =====================================================================
async function verificarSesionActiva() {
  const res = await llamarAPI('sesion_actual', 'GET');
  usuarioActual = (res.ok && res.usuario) ? res.usuario : null;
  actualizarInterfazUsuario();
}

function actualizarInterfazUsuario() {
  const textoEstado = document.getElementById('textoEstadoSesion');
  const btnAbrirAuth = document.getElementById('btnAbrirAuth');
  const btnCerrarSesion = document.getElementById('btnCerrarSesion');
  const navItemAdmin = document.getElementById('navItemAdmin');
  const navItemPerfil = document.getElementById('navItemPerfil');
  const navBuscador = document.getElementById('navBuscador');
  const btnAbrirCarrito = document.getElementById('btnAbrirCarrito');
  const itemsTienda = document.querySelectorAll('.nav-item-tienda');

  const esAdmin = Boolean(usuarioActual && usuarioActual.rol === 'Administrador');
  const esCliente = Boolean(usuarioActual && !esAdmin);

  if (usuarioActual) {
    textoEstado.innerHTML = `<i class="fa-solid fa-user-check"></i> <strong>${usuarioActual.nombre}</strong>`;
    btnAbrirAuth.style.display = 'none';
    btnCerrarSesion.style.display = 'inline-flex';
  } else {
    textoEstado.innerHTML = `<i class="fa-regular fa-user"></i> Visitante`;
    btnAbrirAuth.style.display = 'inline-flex';
    btnCerrarSesion.style.display = 'none';
  }

  navItemAdmin.style.display = esAdmin ? 'block' : 'none';
  if (navItemPerfil) navItemPerfil.style.display = esCliente ? 'block' : 'none';
  if (navBuscador) navBuscador.style.display = esAdmin ? 'none' : '';
  if (btnAbrirCarrito) btnAbrirCarrito.style.display = esAdmin ? 'none' : 'inline-flex';
  itemsTienda.forEach(item => {
    item.style.display = esAdmin ? 'none' : '';
  });

  if (esAdmin) {
    cerrarCarrito();
    mostrarSeccion('panel-admin');
  } else {
    mostrarSeccion('inicio');
  }
}

function evaluarContrasenaSegura(contrasena) {
  return {
    length: contrasena.length >= 8,
    upper: /[A-Z]/.test(contrasena),
    number: /[0-9]/.test(contrasena),
    symbol: /[^A-Za-z0-9]/.test(contrasena)
  };
}

function esContrasenaValida(contrasena) {
  const r = evaluarContrasenaSegura(contrasena);
  return r.length && r.upper && r.number && r.symbol;
}

function actualizarChecklistContrasena(contrasena, listId) {
  const lista = document.getElementById(listId);
  if (!lista) return;
  const r = evaluarContrasenaSegura(contrasena);

  Object.keys(r).forEach((regla) => {
    const li = lista.querySelector(`li[data-rule="${regla}"]`);
    if (!li) return;
    const cumplida = r[regla];
    li.classList.toggle('valid', cumplida);
    const icono = li.querySelector('i');
    if (icono) {
      icono.className = cumplida ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
    }
  });
}

function abrirModalAuth(tab = 'login') {
  limpiarAlertaAuth();
  cambiarTabAuth(tab);
  volverPaso1Login();
  volverPaso1Registro();
  document.getElementById('modalAuth').classList.add('active');
}

function cerrarModalAuth() {
  document.getElementById('modalAuth').classList.remove('active');
}

function cambiarTabAuth(tab) {
  limpiarAlertaAuth();
  const esLogin = tab === 'login';
  document.getElementById('tabBtnLogin').classList.toggle('active', esLogin);
  document.getElementById('tabBtnRegistro').classList.toggle('active', !esLogin);
  document.getElementById('panelLogin').style.display = esLogin ? 'block' : 'none';
  document.getElementById('panelRegistro').style.display = esLogin ? 'none' : 'block';
}

function mostrarAlertaAuth(mensaje, tipo = 'error') {
  const box = document.getElementById('authMensaje');
  box.textContent = mensaje;
  box.className = `auth-alert ${tipo}`;
  box.style.display = 'block';
}

function limpiarAlertaAuth() {
  const box = document.getElementById('authMensaje');
  if (box) box.style.display = 'none';
}

async function solicitarCodigoLogin(event) {
  event.preventDefault();
  limpiarAlertaAuth();

  const correo = document.getElementById('loginCorreo').value.trim();
  const contrasena = document.getElementById('loginContrasena').value;
  const btn = document.getElementById('btnLoginPaso1');

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Enviando código... <i class="fa-solid fa-spinner fa-spin"></i>';
  }

  const res = await llamarAPI('login_solicitar', 'POST', { correo, contrasena });

  if (btn) {
    btn.disabled = false;
    btn.innerHTML = 'Enviar Código de Verificación <i class="fa-solid fa-paper-plane"></i>';
  }

  if (!res.ok) {
    mostrarAlertaAuth(res.mensaje || 'No se pudo iniciar sesión.');
    return;
  }

  correoPendienteLogin = correo;
  document.getElementById('loginCorreoDestino').textContent = correo;
  document.getElementById('formLoginPaso1').style.display = 'none';
  document.getElementById('formLoginPaso2').style.display = 'block';
  document.getElementById('loginCodigo').value = '';

  mostrarAlertaAuth(res.mensaje, 'success');
}

async function verificarCodigoLogin(event) {
  event.preventDefault();
  limpiarAlertaAuth();

  const codigo = document.getElementById('loginCodigo').value.trim();
  const res = await llamarAPI('login_verificar', 'POST', {
    correo: correoPendienteLogin,
    codigo
  });

  if (!res.ok) {
    mostrarAlertaAuth(res.mensaje || 'Código incorrecto.');
    return;
  }

  usuarioActual = res.usuario;
  actualizarInterfazUsuario();
  cerrarModalAuth();
  mostrarNotificacion(`Bienvenido, ${usuarioActual.nombre}`);
}

function volverPaso1Login() {
  document.getElementById('formLoginPaso1').style.display = 'block';
  document.getElementById('formLoginPaso2').style.display = 'none';
}

async function solicitarCodigoRegistro(event) {
  event.preventDefault();
  limpiarAlertaAuth();

  const nombre = document.getElementById('regNombre').value.trim();
  const correo = document.getElementById('regCorreo').value.trim();
  const telefono = document.getElementById('regTelefono').value.trim();
  const contrasena = document.getElementById('regContrasena').value;

  if (!esContrasenaValida(contrasena)) {
    mostrarAlertaAuth('La contraseña debe tener mínimo 8 caracteres, al menos una mayúscula, un número y un símbolo especial.');
    return;
  }

  const btn = document.getElementById('btnRegistroPaso1');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Enviando código... <i class="fa-solid fa-spinner fa-spin"></i>';
  }

  const res = await llamarAPI('registro_solicitar', 'POST', {
    nombre,
    correo,
    telefono,
    contrasena
  });

  if (btn) {
    btn.disabled = false;
    btn.innerHTML = 'Enviar Código de Verificación <i class="fa-solid fa-paper-plane"></i>';
  }

  if (!res.ok) {
    mostrarAlertaAuth(res.mensaje || 'No se pudo enviar el código.');
    return;
  }

  correoPendienteRegistro = correo;
  document.getElementById('regCorreoDestino').textContent = correo;
  document.getElementById('formRegistroPaso1').style.display = 'none';
  document.getElementById('formRegistroPaso2').style.display = 'block';
  document.getElementById('regCodigo').value = '';

  mostrarAlertaAuth(res.mensaje, 'success');
}

async function verificarCodigoRegistro(event) {
  event.preventDefault();
  limpiarAlertaAuth();

  const codigo = document.getElementById('regCodigo').value.trim();
  const res = await llamarAPI('registro_verificar', 'POST', {
    correo: correoPendienteRegistro,
    codigo
  });

  if (!res.ok) {
    mostrarAlertaAuth(res.mensaje || 'Código incorrecto o expirado.');
    return;
  }

  usuarioActual = res.usuario;
  actualizarInterfazUsuario();
  cerrarModalAuth();
  document.getElementById('formRegistroPaso1').reset();
  actualizarChecklistContrasena('', 'regPasswordChecklist');
  mostrarNotificacion(`¡Cuenta creada! Bienvenido, ${usuarioActual.nombre}`);
}

function volverPaso1Registro() {
  document.getElementById('formRegistroPaso1').style.display = 'block';
  document.getElementById('formRegistroPaso2').style.display = 'none';
}

async function cerrarSesion() {
  await llamarAPI('cerrar_sesion', 'POST');
  usuarioActual = null;
  await cargarCatalogoDesdeServidor();
  actualizarInterfazUsuario();
  mostrarNotificacion('Sesión cerrada.');
}

// =====================================================================
// 5. NAVEGACIÓN ENTRE VISTAS SEPARADAS (CLIENTE vs ADMINISTRADOR)
// =====================================================================
function irAlInicio() {
  if (usuarioActual && usuarioActual.rol === 'Administrador') {
    mostrarSeccion('panel-admin');
  } else {
    mostrarSeccion('inicio');
  }
}

function mostrarSeccion(vista) {
  const vistaTienda = document.getElementById('vistaTienda');
  const vistaPerfil = document.getElementById('vistaPerfil');
  const vistaPanelAdmin = document.getElementById('vistaPanelAdmin');
  const esAdmin = Boolean(usuarioActual && usuarioActual.rol === 'Administrador');

  if (esAdmin) {
    vistaTienda.style.display = 'none';
    if (vistaPerfil) vistaPerfil.style.display = 'none';
    vistaPanelAdmin.style.display = 'block';
    cargarPanelAdmin();
  } else if (vista === 'perfil') {
    if (!usuarioActual) {
      abrirModalAuth('login');
      mostrarAlertaAuth('Inicia sesión para ver tu perfil.', 'error');
      return;
    }
    vistaTienda.style.display = 'none';
    vistaPanelAdmin.style.display = 'none';
    if (vistaPerfil) vistaPerfil.style.display = 'block';
    cargarPerfilUsuario();
  } else {
    if (vista === 'panel-admin') {
      mostrarNotificacion('Acceso exclusivo para administradores.');
    }
    vistaTienda.style.display = 'block';
    if (vistaPerfil) vistaPerfil.style.display = 'none';
    vistaPanelAdmin.style.display = 'none';
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// =====================================================================
// 6. PERFIL DE USUARIO (PEDIDOS, GUÍAS, COMPRAS, EDICIÓN Y ELIMINACIÓN)
// =====================================================================
function mostrarAlertaPerfil(mensaje, tipo = 'success') {
  const box = document.getElementById('perfilMensaje');
  if (!box) return;
  box.textContent = mensaje;
  box.className = `auth-alert ${tipo}`;
  box.style.display = 'block';
}

function limpiarAlertaPerfil() {
  const box = document.getElementById('perfilMensaje');
  if (box) box.style.display = 'none';
}

function cambiarTabPerfil(tabId, boton) {
  limpiarAlertaPerfil();
  document.querySelectorAll('.perfil-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.perfil-pane').forEach(p => p.classList.remove('active'));
  boton.classList.add('active');
  document.getElementById(tabId)?.classList.add('active');
}

async function cargarPerfilUsuario() {
  limpiarAlertaPerfil();
  const res = await llamarAPI('perfil_datos', 'GET');
  if (!res.ok) {
    mostrarAlertaPerfil(res.mensaje || 'No se pudo cargar tu perfil.', 'error');
    return;
  }

  const u = res.usuario;
  const pedidos = res.pedidos || [];

  document.getElementById('perfilNombreTitulo').textContent = u.nombre;
  document.getElementById('perfilSubtitulo').textContent =
    `${u.correo}${u.telefono ? ' • ' + u.telefono : ''} • Miembro desde ${String(u.fecha_registro || '').slice(0, 10)}`;

  document.getElementById('perfilNombre').value = u.nombre || '';
  document.getElementById('perfilCorreo').value = u.correo || '';
  document.getElementById('perfilTelefono').value = u.telefono || '';

  const activos = pedidos.filter(p => p.estado !== 'Entregado');
  const anteriores = pedidos.filter(p => p.estado === 'Entregado');
  const totalGastado = pedidos.reduce((sum, p) => sum + Number(p.total || 0), 0);

  document.getElementById('perfilTotalActivos').textContent = activos.length;
  document.getElementById('perfilTotalEntregadas').textContent = anteriores.length;
  document.getElementById('perfilTotalCompras').textContent = pedidos.length;
  document.getElementById('perfilTotalGastado').textContent = `$${totalGastado.toFixed(2)}`;

  renderizarListaPedidos('listaPerfilPedidos', activos, 'No tienes pedidos activos o en proceso en este momento.', true);
  renderizarListaPedidos('listaPerfilAnteriores', anteriores, 'Aún no tienes compras entregadas anteriormente.', false);
  renderizarListaPedidos('listaPerfilTodas', pedidos, 'Todavía no has realizado ninguna compra en Tacón de Oro.', true);
}

function renderizarListaPedidos(contenedorId, lista, mensajeVacio, permitirCancelar) {
  const el = document.getElementById(contenedorId);
  if (!el) return;

  if (lista.length === 0) {
    el.innerHTML = `
      <div class="empty-card">
        <i class="fa-solid fa-bag-shopping"></i>
        <h3>Sin registros</h3>
        <p>${mensajeVacio}</p>
      </div>
    `;
    return;
  }

  el.innerHTML = lista.map(p => {
    const estadoClase = String(p.estado || 'Pendiente').toLowerCase();
    const articulos = Array.isArray(p.articulos) && p.articulos.length > 0
      ? p.articulos.map(a => `
          <li>
            <span><strong>${a.nombre_articulo}</strong> &mdash; Talla ${Number(a.talla).toFixed(1)} &bull; ${a.color} (x${a.cantidad})</span>
            <strong>$${Number(a.precio).toFixed(2)}</strong>
          </li>
        `).join('')
      : `<li><span>Detalle de compra registrado</span><strong>$${Number(p.total).toFixed(2)}</strong></li>`;

    const infoGuia = p.numero_guia
      ? `<div class="tracking-box">
           <span><i class="fa-solid fa-truck-fast gold-text"></i> <strong>Paquetería:</strong> ${p.paqueteria || 'DHL'}</span>
           <span><strong>Guía de rastreo:</strong> ${p.numero_guia}</span>
           ${p.fecha_envio ? `<span><strong>Enviado:</strong> ${String(p.fecha_envio).slice(0, 16)}</span>` : ''}
           ${p.fecha_entrega ? `<span><strong>Entregado:</strong> ${String(p.fecha_entrega).slice(0, 16)}</span>` : ''}
         </div>`
      : `<div class="tracking-box">
           <span><i class="fa-solid fa-box gold-text"></i> <strong>Envío (${p.paqueteria || 'DHL'}):</strong> Guía de rastreo en preparación</span>
         </div>`;

    const btnCancelar = (permitirCancelar && p.estado === 'Pendiente')
      ? `<button class="btn-cancel-sm" onclick="cancelarPedidoUsuario(${p.id_pedido})"><i class="fa-solid fa-xmark"></i> Cancelar pedido</button>`
      : '';

    return `
      <div class="order-card">
        <div class="order-head">
          <div>
            <strong>Pedido #${p.id_pedido}</strong>
            <span class="order-meta">${p.fecha}${p.referencia ? ' • Ref: ' + p.referencia : ''}</span>
          </div>
          <div class="order-head-right">
            <span class="status-badge ${estadoClase}">${p.estado}</span>
            <strong class="order-total">$${Number(p.total).toFixed(2)} MXN</strong>
          </div>
        </div>
        <ul class="order-items">${articulos}</ul>
        ${infoGuia}
        ${btnCancelar ? `<div class="order-actions">${btnCancelar}</div>` : ''}
      </div>
    `;
  }).join('');
}

async function cancelarPedidoUsuario(idPedido) {
  if (!confirm(`¿Seguro que deseas cancelar el pedido #${idPedido}?`)) return;
  const res = await llamarAPI('perfil_cancelar_pedido', 'POST', { id_pedido: idPedido });
  if (!res.ok) {
    mostrarAlertaPerfil(res.mensaje || 'No se pudo cancelar el pedido.', 'error');
    return;
  }
  mostrarNotificacion(res.mensaje);
  await cargarCatalogoDesdeServidor();
  cargarPerfilUsuario();
}

async function guardarInfoPerfil(event) {
  event.preventDefault();
  limpiarAlertaPerfil();

  const nombre = document.getElementById('perfilNombre').value.trim();
  const correo = document.getElementById('perfilCorreo').value.trim();
  const telefono = document.getElementById('perfilTelefono').value.trim();

  const res = await llamarAPI('perfil_actualizar', 'POST', { nombre, correo, telefono });
  if (!res.ok) {
    mostrarAlertaPerfil(res.mensaje || 'No se pudo actualizar tu información.', 'error');
    return;
  }

  usuarioActual = res.usuario;
  document.getElementById('textoEstadoSesion').innerHTML =
    `<i class="fa-solid fa-user-check"></i> <strong>${usuarioActual.nombre}</strong>`;
  mostrarAlertaPerfil(res.mensaje, 'success');
  cargarPerfilUsuario();
}

async function cambiarContrasenaPerfil(event) {
  event.preventDefault();
  limpiarAlertaPerfil();

  const contrasena_actual = document.getElementById('perfilPassActual').value;
  const contrasena_nueva = document.getElementById('perfilPassNueva').value;

  if (!esContrasenaValida(contrasena_nueva)) {
    mostrarAlertaPerfil('La nueva contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un símbolo especial.', 'error');
    return;
  }

  const res = await llamarAPI('perfil_cambiar_contrasena', 'POST', {
    contrasena_actual,
    contrasena_nueva
  });

  if (!res.ok) {
    mostrarAlertaPerfil(res.mensaje || 'No se pudo cambiar la contraseña.', 'error');
    return;
  }

  document.getElementById('formPerfilPass').reset();
  actualizarChecklistContrasena('', 'perfilPasswordChecklist');
  mostrarAlertaPerfil(res.mensaje, 'success');
}

async function eliminarCuentaUsuario(event) {
  event.preventDefault();
  limpiarAlertaPerfil();

  const contrasena = document.getElementById('eliminarPass').value;
  if (!confirm('¿Estás completamente seguro? Tu cuenta se eliminará de forma definitiva de la base de datos.')) {
    return;
  }

  const res = await llamarAPI('perfil_eliminar_cuenta', 'POST', { contrasena });
  if (!res.ok) {
    mostrarAlertaPerfil(res.mensaje || 'No se pudo eliminar la cuenta.', 'error');
    return;
  }

  document.getElementById('formEliminarCuenta').reset();
  usuarioActual = null;
  carrito = [];
  document.getElementById('contadorCarrito').textContent = '0';
  actualizarInterfazUsuario();
  mostrarNotificacion('Tu cuenta fue eliminada permanentemente.');
}

// =====================================================================
// 7. PANEL DE ADMINISTRACIÓN COMPLETO
// =====================================================================
async function cargarPanelAdmin() {
  document.getElementById('adminBienvenida').textContent =
    `Administrador en sesión: ${usuarioActual.nombre} (${usuarioActual.correo})`;

  const res = await llamarAPI('admin_resumen', 'GET');
  if (!res.ok) {
    mostrarNotificacion(res.mensaje || 'No se pudo cargar el panel de administración.');
    return;
  }

  catalogoAdmin = res.articulos || [];
  const totalPares = catalogoAdmin.reduce((sum, a) => sum + Number(a.stock || 0), 0);
  const ocultos = catalogoAdmin.filter(a => !a.visible).length;

  document.getElementById('adminTotalArticulos').textContent =
    ocultos > 0 ? `${catalogoAdmin.length} (${ocultos} ocultos)` : `${catalogoAdmin.length}`;
  document.getElementById('adminTotalPares').textContent = `${totalPares} pares`;
  document.getElementById('adminTotalUsuarios').textContent = res.usuarios.length;
  document.getElementById('adminTotalPedidos').textContent = res.pedidos.length;

  renderizarTablaAdminProductos(catalogoAdmin);
  renderizarAdminAvisos(res.avisos || []);
  renderizarTablaAdminPedidos(res.pedidos || []);
  usuariosAdmin = res.usuarios || [];
  renderizarTablaAdminUsuarios(usuariosAdmin);
}

function renderizarTablaAdminProductos(articulos) {
  const tbody = document.getElementById('tablaAdminArticulos');
  if (!tbody) return;

  if (articulos.length === 0) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;">No hay productos registrados.</td></tr>`;
    return;
  }

  tbody.innerHTML = articulos.map(art => `
    <tr class="${art.visible ? '' : 'row-hidden-prod'}">
      <td><strong>#${art.id}</strong></td>
      <td>
        <strong>${art.nombre}</strong>
        <div style="font-size:0.76rem; color:#6e665e;">
          ${art.marca} &bull; ${art.categoria} &bull; ${art.linea}
          ${art.esNuevo ? ' &bull; <span style="color:#248255; font-weight:700;">Nuevo</span>' : ''}
        </div>
      </td>
      <td style="font-size:0.78rem;">
        <div><strong>Tallas:</strong> ${art.tallas.map(t => Number(t).toFixed(1)).join(', ')}</div>
        <div><strong>Colores:</strong> ${art.colores.join(', ')}</div>
      </td>
      <td>
        <input type="number" id="admPrecio_${art.id}" class="admin-inline-input" step="0.01" min="1" value="${Number(art.precioBase).toFixed(2)}">
      </td>
      <td>
        <input type="number" id="admDesc_${art.id}" class="admin-inline-input" style="width:68px;" min="0" max="90" value="${art.descuentoPct}">
      </td>
      <td>
        <strong style="color:#a68230;">$${Number(art.precio).toFixed(2)}</strong>
        ${art.descuentoPct > 0 ? `<div style="font-size:0.73rem; color:#c93b3b; font-weight:700;">-${art.descuentoPct}% OFF</div>` : ''}
      </td>
      <td>
        <input type="number" id="admStock_${art.id}" class="admin-inline-input" style="width:72px;" min="0" value="${art.stock}">
      </td>
      <td>
        <span class="status-badge ${art.visible ? 'entregado' : 'pendiente'}">
          ${art.visible ? 'Visible' : 'Oculto'}
        </span>
      </td>
      <td>
        <div class="admin-actions-cell">
          <button class="btn-icon-sm" title="Guardar precio, descuento y stock" onclick="guardarPrecioStockRapido(${art.id})">
            <i class="fa-solid fa-floppy-disk"></i> Guardar
          </button>
          <button class="btn-icon-sm" title="Editar todos los datos" onclick="abrirModalProductoAdmin(${art.id})">
            <i class="fa-solid fa-pen"></i>
          </button>
          <button class="btn-icon-sm" title="${art.visible ? 'Ocultar de la tienda' : 'Mostrar en la tienda'}" onclick="toggleVisibilidadProducto(${art.id}, ${art.visible ? 0 : 1})">
            <i class="fa-solid ${art.visible ? 'fa-eye-slash' : 'fa-eye'}"></i>
          </button>
          <button class="btn-icon-sm danger" title="Eliminar producto de la base de datos" onclick="eliminarProductoAdmin(${art.id})">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

async function guardarPrecioStockRapido(idArticulo) {
  const precio_base = parseFloat(document.getElementById(`admPrecio_${idArticulo}`)?.value || '0');
  const descuento_pct = parseInt(document.getElementById(`admDesc_${idArticulo}`)?.value || '0', 10);
  const stock = parseInt(document.getElementById(`admStock_${idArticulo}`)?.value || '0', 10);

  const res = await llamarAPI('admin_precio_descuento', 'POST', {
    id_articulo: idArticulo,
    precio_base,
    descuento_pct,
    stock
  });

  mostrarNotificacion(res.mensaje || 'Actualizado.');
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

async function toggleVisibilidadProducto(idArticulo, nuevoEstado) {
  const res = await llamarAPI('admin_visibilidad_producto', 'POST', {
    id_articulo: idArticulo,
    visible: nuevoEstado
  });
  mostrarNotificacion(res.mensaje || 'Visibilidad actualizada.');
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

async function eliminarProductoAdmin(idArticulo) {
  const prod = catalogoAdmin.find(a => a.id === idArticulo);
  if (!confirm(`¿Eliminar permanentemente "${prod ? prod.nombre : '#' + idArticulo}" de la base de datos?`)) {
    return;
  }

  const res = await llamarAPI('admin_eliminar_producto', 'POST', { id_articulo: idArticulo });
  mostrarNotificacion(res.mensaje || 'Producto eliminado.');
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

function abrirModalProductoAdmin(idArticulo = 0) {
  const titulo = document.getElementById('tituloModalProductoAdmin');
  const prod = catalogoAdmin.find(a => a.id === idArticulo);

  if (prod) {
    titulo.textContent = `Editar Producto #${prod.id}`;
    document.getElementById('adminProdId').value = prod.id;
    document.getElementById('adminProdNombre').value = prod.nombre;
    document.getElementById('adminProdMarca').value = prod.marca;
    document.getElementById('adminProdCategoria').value = prod.categoria;
    document.getElementById('adminProdLinea').value = prod.linea;
    document.getElementById('adminProdPrecio').value = Number(prod.precioBase).toFixed(2);
    document.getElementById('adminProdDescuento').value = prod.descuentoPct;
    document.getElementById('adminProdStock').value = prod.stock;
    document.getElementById('adminProdTallas').value = prod.tallas.map(t => Number(t).toFixed(1)).join(', ');
    document.getElementById('adminProdColores').value = prod.colores.join(', ');
    document.getElementById('adminProdDescripcion').value = prod.descripcion || '';
    document.getElementById('adminProdNuevo').checked = Boolean(prod.esNuevo);
    document.getElementById('adminProdVisible').checked = Boolean(prod.visible);
  } else {
    titulo.textContent = 'Agregar Nuevo Producto al Catálogo';
    document.getElementById('formAdminProducto').reset();
    document.getElementById('adminProdId').value = '0';
    document.getElementById('adminProdMarca').value = 'Nike';
    document.getElementById('adminProdDescuento').value = '0';
    document.getElementById('adminProdStock').value = '15';
    document.getElementById('adminProdNuevo').checked = true;
    document.getElementById('adminProdVisible').checked = true;
  }

  document.getElementById('modalAdminProducto').classList.add('active');
}

function cerrarModalProductoAdmin() {
  document.getElementById('modalAdminProducto').classList.remove('active');
}

async function guardarProductoModalAdmin(event) {
  event.preventDefault();

  const payload = {
    id_articulo: parseInt(document.getElementById('adminProdId').value, 10) || 0,
    nombre: document.getElementById('adminProdNombre').value.trim(),
    marca: document.getElementById('adminProdMarca').value.trim(),
    categoria: document.getElementById('adminProdCategoria').value,
    linea: document.getElementById('adminProdLinea').value,
    precio_base: parseFloat(document.getElementById('adminProdPrecio').value),
    descuento_pct: parseInt(document.getElementById('adminProdDescuento').value, 10) || 0,
    stock: parseInt(document.getElementById('adminProdStock').value, 10) || 0,
    tallas: document.getElementById('adminProdTallas').value,
    colores: document.getElementById('adminProdColores').value,
    descripcion: document.getElementById('adminProdDescripcion').value.trim(),
    es_nuevo: document.getElementById('adminProdNuevo').checked ? 1 : 0,
    visible: document.getElementById('adminProdVisible').checked ? 1 : 0
  };

  const res = await llamarAPI('admin_guardar_producto', 'POST', payload);
  mostrarNotificacion(res.mensaje || 'Guardado.');
  if (res.ok) {
    cerrarModalProductoAdmin();
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

async function aplicarDescuentoMasivoAdmin(event) {
  event.preventDefault();
  const objetivo = document.getElementById('adminMasivoObjetivo').value;
  const descuento_pct = parseInt(document.getElementById('adminMasivoPct').value, 10) || 0;

  const res = await llamarAPI('admin_descuento_temporada', 'POST', { objetivo, descuento_pct });
  mostrarNotificacion(res.mensaje);
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

async function quitarDescuentoMasivoAdmin() {
  const objetivo = document.getElementById('adminMasivoObjetivo').value;
  const res = await llamarAPI('admin_descuento_temporada', 'POST', { objetivo, descuento_pct: 0 });
  mostrarNotificacion(res.mensaje);
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

// --- AVISOS DE TEMPORADA (ADMIN) ---
function renderizarAdminAvisos(avisos) {
  const cont = document.getElementById('listaAdminAvisos');
  if (!cont) return;

  if (avisos.length === 0) {
    cont.innerHTML = `<p style="color:#6e665e; font-size:0.88rem;">No hay avisos de temporada registrados.</p>`;
    return;
  }

  cont.innerHTML = avisos.map(a => {
    const activo = Number(a.activo) === 1;
    return `
      <div class="order-card">
        <div class="order-head">
          <div>
            <strong>${a.titulo}</strong>
            ${a.etiqueta_descuento ? `<span class="badge-pill badge-oferta" style="position:static; margin-left:6px;">${a.etiqueta_descuento}</span>` : ''}
          </div>
          <span class="status-badge ${activo ? 'entregado' : 'pendiente'}">${activo ? 'Publicado' : 'Oculto'}</span>
        </div>
        <p style="font-size:0.86rem; color:#4a453e;">${a.mensaje}</p>
        <div class="order-actions" style="gap:8px;">
          <button class="btn-icon-sm" onclick="toggleAvisoAdmin(${a.id_aviso}, ${activo ? 0 : 1})">
            <i class="fa-solid ${activo ? 'fa-eye-slash' : 'fa-eye'}"></i> ${activo ? 'Ocultar' : 'Activar'}
          </button>
          <button class="btn-icon-sm danger" onclick="eliminarAvisoAdmin(${a.id_aviso})">
            <i class="fa-solid fa-trash"></i> Eliminar
          </button>
        </div>
      </div>
    `;
  }).join('');
}

async function crearAvisoTemporadaAdmin(event) {
  event.preventDefault();
  const titulo = document.getElementById('avisoTitulo').value.trim();
  const etiqueta_descuento = document.getElementById('avisoEtiqueta').value.trim();
  const mensaje = document.getElementById('avisoMensaje').value.trim();

  const res = await llamarAPI('admin_crear_aviso', 'POST', { titulo, etiqueta_descuento, mensaje });
  mostrarNotificacion(res.mensaje);
  if (res.ok) {
    document.getElementById('formAdminAviso').reset();
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

async function toggleAvisoAdmin(idAviso, activo) {
  const res = await llamarAPI('admin_toggle_aviso', 'POST', { id_aviso: idAviso, activo });
  mostrarNotificacion(res.mensaje);
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

async function eliminarAvisoAdmin(idAviso) {
  if (!confirm('¿Eliminar este aviso de temporada?')) return;
  const res = await llamarAPI('admin_eliminar_aviso', 'POST', { id_aviso: idAviso });
  mostrarNotificacion(res.mensaje);
  if (res.ok) {
    await cargarCatalogoDesdeServidor();
    cargarPanelAdmin();
  }
}

// --- PEDIDOS Y GUÍAS DE ENVÍO (ADMIN) ---
function renderizarTablaAdminPedidos(pedidos) {
  const tbody = document.getElementById('tablaAdminPedidos');
  if (!tbody) return;

  if (pedidos.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:#6e665e;">Aún no hay pedidos registrados en la base de datos.</td></tr>`;
    return;
  }

  const paqueterias = ['DHL', 'FedEx', 'Estafeta', 'Paquetexpress'];

  tbody.innerHTML = pedidos.map(p => {
    const resumenItems = Array.isArray(p.articulos) && p.articulos.length > 0
      ? p.articulos.map(a => `${a.nombre_articulo} (T.${Number(a.talla).toFixed(1)}, ${a.color})`).join(', ')
      : 'Sin detalle';

    return `
      <tr>
        <td>
          <strong>#${p.id_pedido}</strong>
          <div style="font-size:0.74rem; color:#6e665e;">${String(p.fecha).slice(0, 16)}</div>
        </td>
        <td>
          <strong>${p.cliente}</strong>
          <div style="font-size:0.75rem; color:#6e665e; max-width:260px;">${resumenItems}</div>
        </td>
        <td><strong>$${Number(p.total).toFixed(2)}</strong></td>
        <td>
          <select id="admPedEstado_${p.id_pedido}" class="form-control" style="padding:6px 8px; width:auto;">
            <option value="Pendiente" ${p.estado === 'Pendiente' ? 'selected' : ''}>Pendiente</option>
            <option value="Empaquetando" ${p.estado === 'Empaquetando' ? 'selected' : ''}>Empaquetando</option>
            <option value="Entregado" ${p.estado === 'Entregado' ? 'selected' : ''}>Entregado</option>
          </select>
        </td>
        <td>
          <select id="admPedPaq_${p.id_pedido}" class="form-control" style="padding:6px 8px; width:auto;">
            ${paqueterias.map(pq => `<option value="${pq}" ${(p.paqueteria || 'DHL') === pq ? 'selected' : ''}>${pq}</option>`).join('')}
          </select>
        </td>
        <td>
          <input type="text" id="admPedGuia_${p.id_pedido}" class="admin-inline-input" style="width:145px;" placeholder="Ej. DHL98451200MX" value="${p.numero_guia || ''}">
        </td>
        <td>
          <button class="btn-icon-sm" onclick="guardarPedidoGuiaAdmin(${p.id_pedido})">
            <i class="fa-solid fa-floppy-disk"></i> Guardar
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

async function guardarPedidoGuiaAdmin(idPedido) {
  const estado = document.getElementById(`admPedEstado_${idPedido}`).value;
  const paqueteria = document.getElementById(`admPedPaq_${idPedido}`).value;
  const numero_guia = document.getElementById(`admPedGuia_${idPedido}`).value.trim();

  const res = await llamarAPI('admin_actualizar_pedido_envio', 'POST', {
    id_pedido: idPedido,
    estado,
    paqueteria,
    numero_guia
  });

  mostrarNotificacion(res.mensaje || 'Pedido actualizado.');
  if (res.ok) {
    cargarPanelAdmin();
  }
}

function renderizarTablaAdminUsuarios(usuarios) {
  const tbody = document.getElementById('tablaAdminUsuarios');
  if (!tbody) return;

  if (usuarios.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No hay usuarios registrados.</td></tr>`;
    return;
  }

  tbody.innerHTML = usuarios.map(u => {
    const esMismoAdmin = usuarioActual && Number(u.id_usuario) === Number(usuarioActual.id_usuario);
    return `
      <tr>
        <td><strong>#${u.id_usuario}</strong></td>
        <td>
          <strong>${u.nombre}</strong>
          ${esMismoAdmin ? '<span style="font-size:0.74rem; color:#a68230; font-weight:700;"> (Tú)</span>' : ''}
        </td>
        <td>${u.correo}</td>
        <td>
          <span class="role-badge ${u.rol === 'Administrador' ? 'admin' : 'cliente'}">
            ${u.rol}
          </span>
        </td>
        <td>${u.telefono || '—'}</td>
        <td style="font-size:0.78rem; color:#6e665e;">${String(u.fecha_registro || '').slice(0, 10)}</td>
        <td>
          <div class="admin-actions-cell">
            <button class="btn-icon-sm" title="Editar información o contraseña" onclick="abrirModalUsuarioAdmin(${u.id_usuario})">
              <i class="fa-solid fa-user-pen"></i> Editar
            </button>
            ${esMismoAdmin ? '' : `
              <button class="btn-icon-sm danger" title="Eliminar usuario de la base de datos" onclick="eliminarUsuarioAdmin(${u.id_usuario})">
                <i class="fa-solid fa-trash"></i> Eliminar
              </button>
            `}
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function abrirModalUsuarioAdmin(idUsuario = 0) {
  const titulo = document.getElementById('tituloModalUsuarioAdmin');
  const labelPass = document.getElementById('labelAdminUserPass');
  const inputPass = document.getElementById('adminUserPass');
  const selectRol = document.getElementById('adminUserRol');
  const u = usuariosAdmin.find(item => Number(item.id_usuario) === Number(idUsuario));

  document.getElementById('formAdminUsuario').reset();
  actualizarChecklistContrasena('', 'adminUserPasswordChecklist');

  if (u) {
    const esMismoAdmin = usuarioActual && Number(u.id_usuario) === Number(usuarioActual.id_usuario);
    titulo.textContent = `Editar ${u.rol} #${u.id_usuario}`;
    document.getElementById('adminUserId').value = u.id_usuario;
    document.getElementById('adminUserNombre').value = u.nombre || '';
    document.getElementById('adminUserCorreo').value = u.correo || '';
    document.getElementById('adminUserTelefono').value = u.telefono || '';
    selectRol.value = u.rol;
    selectRol.disabled = Boolean(esMismoAdmin);
    labelPass.textContent = 'Nueva contraseña (dejar vacío para conservar la actual)';
    inputPass.required = false;
  } else {
    titulo.textContent = 'Dar de Alta Usuario / Administrador';
    document.getElementById('adminUserId').value = '0';
    selectRol.value = 'Administrador';
    selectRol.disabled = false;
    labelPass.textContent = 'Contraseña segura (obligatoria)';
    inputPass.required = true;
  }

  document.getElementById('modalAdminUsuario').classList.add('active');
}

function cerrarModalUsuarioAdmin() {
  document.getElementById('modalAdminUsuario').classList.remove('active');
}

async function guardarUsuarioModalAdmin(event) {
  event.preventDefault();

  const id_usuario = parseInt(document.getElementById('adminUserId').value, 10) || 0;
  const nombre = document.getElementById('adminUserNombre').value.trim();
  const correo = document.getElementById('adminUserCorreo').value.trim();
  const telefono = document.getElementById('adminUserTelefono').value.trim();
  const rol = document.getElementById('adminUserRol').value;
  const contrasena = document.getElementById('adminUserPass').value;

  if ((id_usuario === 0 || contrasena !== '') && !esContrasenaValida(contrasena)) {
    mostrarNotificacion('La contraseña debe tener mínimo 8 caracteres, mayúscula, número y símbolo especial.');
    return;
  }

  const res = await llamarAPI('admin_guardar_usuario', 'POST', {
    id_usuario,
    nombre,
    correo,
    telefono,
    rol,
    contrasena
  });

  mostrarNotificacion(res.mensaje || 'Guardado.');
  if (res.ok) {
    if (res.usuario_sesion) {
      usuarioActual = res.usuario_sesion;
      document.getElementById('textoEstadoSesion').innerHTML =
        `<i class="fa-solid fa-user-check"></i> <strong>${usuarioActual.nombre}</strong>`;
    }
    cerrarModalUsuarioAdmin();
    cargarPanelAdmin();
  }
}

async function eliminarUsuarioAdmin(idUsuario) {
  const u = usuariosAdmin.find(item => Number(item.id_usuario) === Number(idUsuario));
  if (!confirm(`¿Eliminar permanentemente a "${u ? u.nombre : '#' + idUsuario}" (${u ? u.rol : ''}) de la base de datos?`)) {
    return;
  }

  const res = await llamarAPI('admin_eliminar_usuario', 'POST', { id_usuario: idUsuario });
  mostrarNotificacion(res.mensaje || 'Usuario eliminado.');
  if (res.ok) {
    cargarPanelAdmin();
  }
}

function cambiarTabAdmin(tabId, boton) {
  document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.admin-pane').forEach(p => p.classList.remove('active'));
  boton.classList.add('active');
  document.getElementById(tabId)?.classList.add('active');
}

// =====================================================================
// 8. CATÁLOGO PÚBLICO, TARJETAS CON COLORES DEL PDF Y FILTROS
// =====================================================================
function crearFiltrosTallas() {
  const contenedor = document.getElementById('contenedorTallas');
  if (!contenedor) return;

  const tallas = [17.0, 18.0, 19.0, 20.0, 22.0, 22.5, 23.0, 23.5, 24.0, 24.5, 25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0, 28.5];
  contenedor.innerHTML = tallas.map(t => `
    <button class="size-btn" onclick="seleccionarFiltroTalla(${t}, this)">${t.toFixed(1)}</button>
  `).join('');
}

function crearFiltrosColores() {
  const contenedor = document.getElementById('contenedorColores');
  if (!contenedor) return;

  contenedor.innerHTML = Object.entries(MAPA_COLORES).map(([nombre, hex]) => `
    <button class="color-chip" onclick="seleccionarFiltroColor('${nombre}', this)">
      <span class="color-dot" style="background:${hex};"></span> ${nombre}
    </button>
  `).join('');
}

function renderizarSeccionesInicio() {
  const gridOfertas = document.getElementById('gridOfertas');
  const gridNovedades = document.getElementById('gridNovedades');

  const ofertas = catalogoTienda.filter(a => a.esOferta);
  const novedades = catalogoTienda.filter(a => a.esNuevo);

  if (gridOfertas) {
    gridOfertas.innerHTML = ofertas.length > 0
      ? ofertas.map(crearTarjetaArticulo).join('')
      : `<p style="color:#6e665e;">No hay ofertas activas en este momento.</p>`;
  }

  if (gridNovedades) {
    gridNovedades.innerHTML = novedades.length > 0
      ? novedades.map(crearTarjetaArticulo).join('')
      : `<p style="color:#6e665e;">Próximamente nuevos lanzamientos.</p>`;
  }

  renderizarCatalogo();
}

function crearTarjetaArticulo(art) {
  let badge = '';
  if (art.esOferta) {
    badge = `<span class="badge-pill badge-oferta">Oferta -${art.descuentoPct}%</span>`;
  } else if (art.esNuevo) {
    badge = '<span class="badge-pill badge-nuevo">Nuevo</span>';
  }

  const swatches = art.colores.map(c => {
    const hex = MAPA_COLORES[c] || '#ccc';
    return `<span class="swatch-dot" style="background:${hex};" title="${c}"></span>`;
  }).join('');

  const agotado = Number(art.stock) <= 0;

  return `
    <article class="article-card">
      <div class="article-placeholder">
        ${badge}
        <i class="fa-solid fa-shoe-prints"></i>
        <span class="placeholder-label">${art.marca}</span>
      </div>

      <div class="article-body">
        <span class="article-meta">${art.marca} &bull; ${art.categoria} &bull; ${art.linea}</span>
        <h3 class="article-title">${art.nombre}</h3>

        <div class="article-specs">
          <div class="spec-row">
            <span>Tallas</span>
            <strong>${art.tallas.map(t => Number(t).toFixed(1)).join(', ')}</strong>
          </div>
          <div class="spec-row">
            <span>Colores</span>
            <span class="card-swatches">${swatches}</span>
          </div>
          <div class="spec-row">
            <span>Stock</span>
            <strong style="${agotado ? 'color:#c93b3b;' : ''}">${agotado ? 'Agotado' : art.stock + ' pares'}</strong>
          </div>
        </div>

        <div class="article-footer">
          <div>
            ${art.precioAnterior ? `<span class="price-old">$${Number(art.precioAnterior).toFixed(2)}</span>` : ''}
            <span class="price-current">$${Number(art.precio).toFixed(2)}</span>
          </div>
          <button class="btn-buy-sm" onclick="abrirModalArticulo(${art.id})" ${agotado ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}>
            <i class="fa-solid fa-bag-shopping"></i> ${agotado ? 'Sin stock' : 'Elegir'}
          </button>
        </div>
      </div>
    </article>
  `;
}

function renderizarCatalogo() {
  const grid = document.getElementById('gridCatalogo');
  const contador = document.getElementById('contadorArticulos');
  const sinResultados = document.getElementById('sinResultados');
  if (!grid || !contador || !sinResultados) return;

  const filtrados = catalogoTienda.filter(art => {
    if (filtros.busqueda) {
      const texto = `${art.nombre} ${art.marca} ${art.descripcion} ${art.colores.join(' ')}`.toLowerCase();
      if (!texto.includes(filtros.busqueda)) return false;
    }
    if (filtros.categoria !== 'todas' && art.categoria !== filtros.categoria) return false;
    if (filtros.marca !== 'todas' && art.marca !== filtros.marca) return false;
    if (filtros.linea !== 'todos' && art.linea !== filtros.linea) return false;
    if (filtros.talla !== null && !art.tallas.includes(filtros.talla)) return false;
    if (filtros.color !== null && !art.colores.includes(filtros.color)) return false;
    if (Number(art.precio) > filtros.precioMaximo) return false;
    return true;
  });

  contador.textContent = `Mostrando ${filtrados.length} modelo(s) de calzado`;

  if (filtrados.length === 0) {
    grid.innerHTML = '';
    sinResultados.style.display = 'block';
  } else {
    sinResultados.style.display = 'none';
    grid.innerHTML = filtrados.map(crearTarjetaArticulo).join('');
  }
}

function aplicarFiltroCategoria(categoria, boton) {
  filtros.categoria = categoria;
  document.querySelectorAll('.chip').forEach(b => b.classList.remove('active'));
  boton.classList.add('active');
  renderizarCatalogo();
}

function seleccionarFiltroTalla(talla, boton) {
  if (filtros.talla === talla) {
    filtros.talla = null;
    boton.classList.remove('active');
  } else {
    filtros.talla = talla;
    document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
    boton.classList.add('active');
  }
  renderizarCatalogo();
}

function seleccionarFiltroColor(color, boton) {
  if (filtros.color === color) {
    filtros.color = null;
    boton.classList.remove('active');
  } else {
    filtros.color = color;
    document.querySelectorAll('.color-chip').forEach(b => b.classList.remove('active'));
    boton.classList.add('active');
  }
  renderizarCatalogo();
}

function limpiarTodosLosFiltros() {
  filtros.busqueda = '';
  filtros.categoria = 'todas';
  filtros.marca = 'todas';
  filtros.linea = 'todos';
  filtros.talla = null;
  filtros.color = null;
  filtros.precioMaximo = 5000;

  document.getElementById('inputBuscar').value = '';
  if (document.getElementById('filtroMarca')) document.getElementById('filtroMarca').value = 'todas';
  document.getElementById('filtroLinea').value = 'todos';
  document.getElementById('rangoPrecio').value = 5000;
  document.getElementById('textoPrecioMaximo').textContent = '$5,000';

  document.querySelectorAll('.chip').forEach((b, i) => b.classList.toggle('active', i === 0));
  document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.color-chip').forEach(b => b.classList.remove('active'));

  renderizarCatalogo();
  mostrarNotificacion('Filtros restablecidos.');
}

// =====================================================================
// 9. MODAL DE ARTÍCULO Y CARRITO DE COMPRAS
// =====================================================================
function abrirModalArticulo(id) {
  articuloSeleccionado = catalogoTienda.find(a => a.id === id);
  if (!articuloSeleccionado || Number(articuloSeleccionado.stock) <= 0) return;

  document.getElementById('contenidoModalArticulo').innerHTML = `
    <div class="article-placeholder" style="height:130px; border-radius:10px; margin-bottom:16px;">
      <i class="fa-solid fa-shoe-prints"></i>
      <span class="placeholder-label">${articuloSeleccionado.marca}</span>
    </div>
    <span class="article-meta">${articuloSeleccionado.marca} &bull; ${articuloSeleccionado.categoria} &bull; ${articuloSeleccionado.linea}</span>
    <h2 style="font-size:1.5rem; margin:4px 0 8px;">${articuloSeleccionado.nombre}</h2>
    <p style="font-size:0.88rem; color:#6e665e; margin-bottom:12px;">${articuloSeleccionado.descripcion}</p>
    <div style="margin-bottom:16px;">
      ${articuloSeleccionado.precioAnterior ? `<span class="price-old" style="margin-right:8px;">$${Number(articuloSeleccionado.precioAnterior).toFixed(2)}</span>` : ''}
      <span style="font-size:1.45rem; font-weight:700; color:#a68230;">$${Number(articuloSeleccionado.precio).toFixed(2)} MXN</span>
      <span style="font-size:0.8rem; color:#6e665e; display:block; margin-top:2px;">Disponibles: ${articuloSeleccionado.stock} pares</span>
    </div>

    <div class="form-group">
      <label for="modalTalla">Talla (MX)</label>
      <select id="modalTalla" class="form-control">
        ${articuloSeleccionado.tallas.map(t => `<option value="${t}">${Number(t).toFixed(1)} MX</option>`).join('')}
      </select>
    </div>

    <div class="form-group">
      <label for="modalColor">Color</label>
      <select id="modalColor" class="form-control">
        ${articuloSeleccionado.colores.map(c => `<option value="${c}">${c}</option>`).join('')}
      </select>
    </div>

    <button class="btn btn-gold full-width" onclick="agregarAlCarrito()">
      <i class="fa-solid fa-bag-shopping"></i> Agregar al Carrito
    </button>
  `;

  document.getElementById('modalArticulo').classList.add('active');
}

function cerrarModalArticulo() {
  document.getElementById('modalArticulo').classList.remove('active');
}

function agregarAlCarrito() {
  if (!articuloSeleccionado) return;

  const talla = parseFloat(document.getElementById('modalTalla').value);
  const color = document.getElementById('modalColor').value;

  carrito.push({
    id: articuloSeleccionado.id,
    nombre: articuloSeleccionado.nombre,
    precio: Number(articuloSeleccionado.precio),
    talla,
    color
  });

  document.getElementById('contadorCarrito').textContent = carrito.length;
  cerrarModalArticulo();
  mostrarNotificacion(`"${articuloSeleccionado.nombre}" agregado al carrito.`);
}

function abrirCarrito() {
  const lista = document.getElementById('listaCarrito');
  const subtotalEl = document.getElementById('subtotalCarrito');
  const envioEl = document.getElementById('envioCarrito');
  const totalEl = document.getElementById('totalCarrito');

  if (carrito.length === 0) {
    lista.innerHTML = `<p style="text-align:center; color:#6e665e; padding:36px 0;">Tu carrito está vacío.</p>`;
    subtotalEl.textContent = '$0.00';
    envioEl.textContent = '$0.00';
    totalEl.textContent = '$0.00';
  } else {
    let subtotal = 0;
    lista.innerHTML = carrito.map((item, idx) => {
      subtotal += item.precio;
      return `
        <div class="cart-item-row">
          <div>
            <strong>${item.nombre}</strong>
            <div style="font-size:0.76rem; color:#6e665e;">Talla ${item.talla.toFixed(1)} &bull; ${item.color}</div>
            <span style="font-weight:700; color:#a68230;">$${item.precio.toFixed(2)}</span>
          </div>
          <button onclick="quitarDelCarrito(${idx})" style="background:none; border:none; color:#c93b3b;" title="Quitar">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    subtotalEl.textContent = `$${subtotal.toFixed(2)}`;
    envioEl.textContent = `$${COSTO_ENVIO.toFixed(2)}`;
    totalEl.textContent = `$${(subtotal + COSTO_ENVIO).toFixed(2)}`;
  }

  document.getElementById('panelCarrito').classList.add('active');
}

function cerrarCarrito() {
  document.getElementById('panelCarrito').classList.remove('active');
}

function quitarDelCarrito(index) {
  carrito.splice(index, 1);
  document.getElementById('contadorCarrito').textContent = carrito.length;
  abrirCarrito();
}

async function finalizarCompra() {
  if (carrito.length === 0) {
    mostrarNotificacion('Agrega al menos un artículo a tu carrito.');
    return;
  }
  if (!usuarioActual) {
    cerrarCarrito();
    abrirModalAuth('login');
    mostrarAlertaAuth('Inicia sesión o crea una cuenta verificada para finalizar tu compra.', 'error');
    return;
  }

  const res = await llamarAPI('crear_pedido', 'POST', { items: carrito });
  if (!res.ok) {
    mostrarNotificacion(res.mensaje || 'No se pudo registrar tu pedido.');
    return;
  }

  carrito = [];
  document.getElementById('contadorCarrito').textContent = '0';
  cerrarCarrito();
  await cargarCatalogoDesdeServidor();
  mostrarNotificacion(`¡Gracias por tu compra! Pedido #${res.id_pedido} registrado.`);
}

// =====================================================================
// 10. NOTIFICACIÓN FLOTANTE
// =====================================================================
function mostrarNotificacion(texto) {
  const el = document.getElementById('notificacion');
  if (!el) return;
  el.textContent = texto;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3200);
}
