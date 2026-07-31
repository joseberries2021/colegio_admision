<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$mensaje = '';
$error = '';

// ============================================
// OBTENER DATOS PARA SELECTS
// ============================================
$niveles = fetchAll("SELECT * FROM niveles WHERE estado = 1 ORDER BY nombre");
$grados = fetchAll("SELECT g.*, n.nombre as nivel_nombre FROM grados g JOIN niveles n ON g.id_nivel = n.id WHERE g.estado = 1 ORDER BY n.nombre, g.orden");

// ============================================
// AGREGAR DOCUMENTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre_documento']);
    $id_nivel = !empty($_POST['id_nivel']) ? (int)$_POST['id_nivel'] : 'NULL';
    $id_grado = !empty($_POST['id_grado']) ? (int)$_POST['id_grado'] : 'NULL';
    $tipo_colegio = $_POST['tipo_colegio'] ?? 'ambos';
    $tipo_alumno = $_POST['tipo_alumno'] ?? 'ambos';
    $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;
    $orden = (int)$_POST['orden'];
    
    if (!empty($nombre)) {
        $sql = "INSERT INTO config_documentos (nombre_documento, id_nivel, id_grado, tipo_colegio, tipo_alumno, obligatorio, orden) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $params = [$nombre, $id_nivel === 'NULL' ? null : $id_nivel, $id_grado === 'NULL' ? null : $id_grado, $tipo_colegio, $tipo_alumno, $obligatorio, $orden];
        insert($sql, $params);
        $mensaje = "✅ Documento agregado correctamente";
    } else {
        $error = "❌ El nombre del documento es obligatorio";
    }
}

// ============================================
// EDITAR DOCUMENTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre_documento']);
    $id_nivel = !empty($_POST['id_nivel']) ? (int)$_POST['id_nivel'] : 'NULL';
    $id_grado = !empty($_POST['id_grado']) ? (int)$_POST['id_grado'] : 'NULL';
    $tipo_colegio = $_POST['tipo_colegio'] ?? 'ambos';
    $tipo_alumno = $_POST['tipo_alumno'] ?? 'ambos';
    $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;
    $orden = (int)$_POST['orden'];
    
    if (!empty($nombre)) {
        $sql = "UPDATE config_documentos SET nombre_documento = ?, id_nivel = ?, id_grado = ?, tipo_colegio = ?, tipo_alumno = ?, obligatorio = ?, orden = ? WHERE id = ?";
        $params = [$nombre, $id_nivel === 'NULL' ? null : $id_nivel, $id_grado === 'NULL' ? null : $id_grado, $tipo_colegio, $tipo_alumno, $obligatorio, $orden, $id];
        query($sql, $params);
        $mensaje = "✅ Documento actualizado correctamente";
    } else {
        $error = "❌ El nombre del documento es obligatorio";
    }
}

// ============================================
// ELIMINAR DOCUMENTO
// ============================================
if (isset($_GET['eliminar']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $tiene_subidos = fetchOne("SELECT COUNT(*) as total FROM documentos_subidos WHERE id_documento_requerido = ?", [$id]);
    if ($tiene_subidos['total'] > 0) {
        $error = "❌ No se puede eliminar porque hay documentos subidos asociados";
    } else {
        query("DELETE FROM config_documentos WHERE id = ?", [$id]);
        $mensaje = "✅ Documento eliminado correctamente";
    }
}

// ============================================
// OBTENER LISTA DE DOCUMENTOS
// ============================================
$documentos = fetchAll("
    SELECT cd.*, 
           n.nombre as nivel_nombre, 
           g.nombre as grado_nombre
    FROM config_documentos cd
    LEFT JOIN niveles n ON cd.id_nivel = n.id
    LEFT JOIN grados g ON cd.id_grado = g.id
    ORDER BY cd.tipo_alumno, cd.tipo_colegio, cd.orden
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Documentos - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; }
        .btn-primary { background: #1a3a6b; border: none; }
        .btn-primary:hover { background: #2d6bb8; }
        .btn-success { background: #2e7d32; border: none; }
        .btn-success:hover { background: #388e3c; }
        .btn-danger { background: #c62828; border: none; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-warning { background: #f57c00; border: none; color: white; }
        .btn-warning:hover { background: #e65100; color: white; }
        .text-primary-dark { color: #1a3a6b; }
        .card-dashboard { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .badge-doc { font-size: 10px; padding: 3px 10px; border-radius: 12px; }
        .filtro-busqueda { margin-bottom: 15px; }
        .filtro-busqueda input { max-width: 300px; }
        .table-sortable th { cursor: pointer; user-select: none; }
        .table-sortable th:hover { background: #e8f0fe; }
        .sidebar-link {
            color: #333;
            text-decoration: none;
            padding: 8px 15px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .sidebar-link:hover { background: #e8f0fe; color: #1a3a6b; }
        .sidebar-link.active { background: #1a3a6b; color: white; }
        .sidebar-link i { margin-right: 10px; width: 20px; text-align: center; }
        .nav-link-custom {
            color: #333;
            text-decoration: none;
            padding: 8px 15px 8px 35px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 13px;
        }
        .nav-link-custom:hover { background: #e8f0fe; color: #1a3a6b; }
        .nav-link-custom.active { background: #1a3a6b; color: white; }
        .nav-link-custom i { margin-right: 8px; width: 16px; text-align: center; }
        .sidebar-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 1px;
            padding: 8px 15px;
            font-weight: 700;
        }
        .sidebar {
            background: white;
            border-radius: 16px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background: #1a3a6b;">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <img src="../assets/img/LOGO%201000X1000%20EN%20BLANCO.png" alt="Logo" height="40" class="d-inline-block align-text-top">
            Admisión 2027 - Admin
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_nombre'] ?? 'Admin'; ?></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-2">
            <div class="sidebar">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-grid-3x3-gap-fill text-primary-dark me-2"></i>
                    <span class="fw-bold text-primary-dark">Menú</span>
                </div>
                <hr>
                <a href="index.php" class="sidebar-link">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="postulantes.php" class="sidebar-link">
                    <i class="bi bi-people"></i> Alumnos & Postulantes
                </a>
                <a href="alumnos_antiguos.php" class="sidebar-link">
                    <i class="bi bi-clock-history"></i> Alumnos Antiguos (Fase 3)
                </a>
                <a href="documentos.php" class="sidebar-link">
                    <i class="bi bi-files"></i> Revisión de Documentos
                </a>
                <div class="sidebar-link" style="cursor: default;">
                    <i class="bi bi-credit-card"></i> Pagos
                </div>
                <a href="pagos.php" class="nav-link-custom">
                    <i class="bi bi-check-circle"></i> Validación de Pagos
                </a>
                <a href="codigos_pago.php" class="nav-link-custom">
                    <i class="bi bi-upc-scan"></i> Códigos de Pago
                </a>
                <a href="citas.php" class="sidebar-link">
                    <i class="bi bi-calendar"></i> Citas Psicológicas
                </a>
                <a href="configuracion.php" class="sidebar-link">
                    <i class="bi bi-geo-alt"></i> Sedes, Distritos y Vacantes
                </a>
                <a href="config_documentos.php" class="sidebar-link active">
                    <i class="bi bi-file-earmark-text"></i> Configurar Documentos
                </a>
                <a href="descuentos.php" class="sidebar-link">
                    <i class="bi bi-percent"></i> Descuentos y Campañas
                </a>
                <a href="contratos.php" class="sidebar-link">
                    <i class="bi bi-file-text"></i> Contratos y Reglamentos
                </a>
                <a href="seguridad.php" class="sidebar-link">
                    <i class="bi bi-shield-lock"></i> Bitácora de Auditoría
                </a>
                <a href="reportes.php" class="sidebar-link">
                    <i class="bi bi-bar-chart"></i> Reportes & Descargas
                </a>
                <hr>
                <div class="sidebar-title">Control de Usuarios</div>
                <a href="control_usuarios.php" class="sidebar-link">
                    <i class="bi bi-person-gear"></i> Control de Usuarios
                </a>
                <a href="matriz_permisos.php" class="sidebar-link">
                    <i class="bi bi-table"></i> Matriz de Permisos
                </a>
            </div>
        </div>
        
        <div class="col-md-10">
            <h4><i class="bi bi-file-earmark-text"></i> Configuración de Documentos</h4>
            <p class="text-muted">Gestiona los documentos requeridos según tipo de colegio, tipo de alumno, nivel y grado</p>

            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Formulario para agregar documento -->
            <div class="card-dashboard mb-4">
                <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Agregar Documento Requerido</h6>
                <hr>
                <form method="POST" class="row g-3" id="formDocumento">
                    <input type="hidden" name="agregar" value="1">
                    
                    <div class="col-md-4">
                        <label class="form-label">Nombre del Documento *</label>
                        <input type="text" name="nombre_documento" class="form-control" placeholder="Ej. DNI del apoderado (Anverso)" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Tipo de Colegio</label>
                        <select name="tipo_colegio" class="form-select">
                            <option value="ambos">Ambos</option>
                            <option value="particular">Particular</option>
                            <option value="estatal">Estatal</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Tipo de Alumno</label>
                        <select name="tipo_alumno" class="form-select">
                            <option value="ambos">Ambos</option>
                            <option value="nuevo">Nuevo</option>
                            <option value="antiguo">Antiguo</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Nivel</label>
                        <select name="id_nivel" class="form-select" id="selectNivel">
                            <option value="">Todos</option>
                            <?php foreach ($niveles as $n): ?>
                                <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Grado</label>
                        <select name="id_grado" class="form-select" id="selectGrado">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="0">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-center">
                        <div class="form-check">
                            <input type="checkbox" name="obligatorio" class="form-check-input" id="obligatorio" checked>
                            <label class="form-check-label" for="obligatorio">Obligatorio</label>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Agregar Documento</button>
                    </div>
                </form>
            </div>

            <!-- Lista de documentos -->
            <div class="card-dashboard">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="text-primary-dark"><i class="bi bi-list"></i> Documentos Configurados</h6>
                    <span class="badge bg-primary">Total: <?php echo count($documentos); ?></span>
                </div>
                <hr>
                
                <div class="filtro-busqueda">
                    <input type="text" id="buscarDocumento" class="form-control form-control-sm" placeholder="🔍 Buscar documento..." onkeyup="filtrarTabla('tablaDocumentos', 'buscarDocumento')">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sortable" id="tablaDocumentos">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabla('tablaDocumentos', 0)">Documento <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDocumentos', 1)">Tipo Colegio <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDocumentos', 2)">Tipo Alumno <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDocumentos', 3)">Nivel <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDocumentos', 4)">Grado <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDocumentos', 5)">Oblig. <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDocumentos', 6)">Orden <i class="bi bi-arrow-up-down"></i></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documentos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox" style="font-size: 30px; display: block;"></i>
                                        No hay documentos configurados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documentos as $d): ?>
                                    <tr>
                                        <td><strong><?php echo $d['nombre_documento']; ?></strong></td>
                                        <td>
                                            <span class="badge-doc bg-<?php 
                                                echo $d['tipo_colegio'] == 'ambos' ? 'primary' : 
                                                    ($d['tipo_colegio'] == 'particular' ? 'info' : 'warning'); 
                                            ?> text-white">
                                                <?php echo ucfirst($d['tipo_colegio']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-doc bg-<?php 
                                                echo $d['tipo_alumno'] == 'ambos' ? 'primary' : 
                                                    ($d['tipo_alumno'] == 'nuevo' ? 'success' : 'secondary'); 
                                            ?> text-white">
                                                <?php echo ucfirst($d['tipo_alumno']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $d['nivel_nombre'] ?? 'Todos'; ?></td>
                                        <td><?php echo $d['grado_nombre'] ?? 'Todos'; ?></td>
                                        <td>
                                            <span class="badge-doc bg-<?php echo $d['obligatorio'] ? 'danger' : 'secondary'; ?> text-white">
                                                <?php echo $d['obligatorio'] ? 'Sí' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $d['orden']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editarDocumentoModal" 
                                                    data-id="<?php echo $d['id']; ?>"
                                                    data-nombre="<?php echo $d['nombre_documento']; ?>"
                                                    data-tipo_colegio="<?php echo $d['tipo_colegio']; ?>"
                                                    data-tipo_alumno="<?php echo $d['tipo_alumno']; ?>"
                                                    data-id_nivel="<?php echo $d['id_nivel']; ?>"
                                                    data-id_grado="<?php echo $d['id_grado']; ?>"
                                                    data-obligatorio="<?php echo $d['obligatorio']; ?>"
                                                    data-orden="<?php echo $d['orden']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="config_documentos.php?eliminar=1&id=<?php echo $d['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Eliminar este documento?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Documento -->
<div class="modal fade" id="editarDocumentoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editId">
                    <input type="hidden" name="editar" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del Documento *</label>
                            <input type="text" name="nombre_documento" id="editNombre" class="form-control" required>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tipo de Colegio</label>
                            <select name="tipo_colegio" id="editTipoColegio" class="form-select">
                                <option value="ambos">Ambos</option>
                                <option value="particular">Particular</option>
                                <option value="estatal">Estatal</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tipo de Alumno</label>
                            <select name="tipo_alumno" id="editTipoAlumno" class="form-select">
                                <option value="ambos">Ambos</option>
                                <option value="nuevo">Nuevo</option>
                                <option value="antiguo">Antiguo</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nivel</label>
                            <select name="id_nivel" id="editNivel" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($niveles as $n): ?>
                                    <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grado</label>
                            <select name="id_grado" id="editGrado" class="form-select">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" id="editOrden" class="form-control">
                        </div>
                        
                        <div class="col-md-2 mb-3 d-flex align-items-center">
                            <div class="form-check">
                                <input type="checkbox" name="obligatorio" id="editObligatorio" class="form-check-input" value="1">
                                <label class="form-check-label" for="editObligatorio">Obligatorio</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filtrarTabla(tablaId, inputId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tablaId);
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < cells.length - 1; j++) {
            const text = cells[j].textContent || cells[j].innerText;
            if (text.toUpperCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        rows[i].style.display = found ? '' : 'none';
    }
}

let sortOrder = {};

function ordenarTabla(tablaId, colIndex) {
    const table = document.getElementById(tablaId);
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));

    if (!sortOrder[tablaId]) sortOrder[tablaId] = {};
    if (!sortOrder[tablaId][colIndex]) sortOrder[tablaId][colIndex] = 'asc';
    else if (sortOrder[tablaId][colIndex] === 'asc') sortOrder[tablaId][colIndex] = 'desc';
    else sortOrder[tablaId][colIndex] = 'asc';

    const order = sortOrder[tablaId][colIndex];

    rows.sort((a, b) => {
        const valA = a.getElementsByTagName('td')[colIndex]?.textContent.trim() || '';
        const valB = b.getElementsByTagName('td')[colIndex]?.textContent.trim() || '';
        
        if (!isNaN(valA) && !isNaN(valB)) {
            return order === 'asc' ? parseInt(valA) - parseInt(valB) : parseInt(valB) - parseInt(valA);
        }
        return order === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
    });

    rows.forEach(row => tbody.appendChild(row));
}

document.addEventListener('DOMContentLoaded', function() {
    const selectNivel = document.getElementById('selectNivel');
    const selectGrado = document.getElementById('selectGrado');
    const editNivel = document.getElementById('editNivel');
    const editGrado = document.getElementById('editGrado');

    const gradosPorNivel = {};
    <?php foreach ($grados as $g): ?>
        if (!gradosPorNivel[<?php echo $g['id_nivel']; ?>]) {
            gradosPorNivel[<?php echo $g['id_nivel']; ?>] = [];
        }
        gradosPorNivel[<?php echo $g['id_nivel']; ?>].push({
            id: <?php echo $g['id']; ?>,
            nombre: '<?php echo addslashes($g['nombre']); ?>'
        });
    <?php endforeach; ?>

    function cargarGrados(nivelId, selectElement) {
        selectElement.innerHTML = '<option value="">Todos</option>';
        if (nivelId && gradosPorNivel[nivelId]) {
            gradosPorNivel[nivelId].forEach(g => {
                selectElement.innerHTML += `<option value="${g.id}">${g.nombre}</option>`;
            });
        }
    }

    if (selectNivel && selectGrado) {
        selectNivel.addEventListener('change', function() {
            cargarGrados(this.value, selectGrado);
        });
        if (selectNivel.value) {
            cargarGrados(selectNivel.value, selectGrado);
        }
    }

    if (editNivel && editGrado) {
        editNivel.addEventListener('change', function() {
            cargarGrados(this.value, editGrado);
        });
    }

    const modal = document.getElementById('editarDocumentoModal');
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const nivelId = button.getAttribute('data-id_nivel') || '';
        const gradoId = button.getAttribute('data-id_grado') || '';
        
        document.getElementById('editId').value = button.getAttribute('data-id');
        document.getElementById('editNombre').value = button.getAttribute('data-nombre');
        document.getElementById('editTipoColegio').value = button.getAttribute('data-tipo_colegio');
        document.getElementById('editTipoAlumno').value = button.getAttribute('data-tipo_alumno');
        document.getElementById('editNivel').value = nivelId;
        document.getElementById('editOrden').value = button.getAttribute('data-orden') || 0;
        document.getElementById('editObligatorio').checked = button.getAttribute('data-obligatorio') == '1';
        
        cargarGrados(nivelId, editGrado);
        if (gradoId) {
            editGrado.value = gradoId;
        }
    });
});
</script>

</body>
</html>