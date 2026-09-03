<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

// 1. Auto-Migração para Agenda
try {
    $pdo->query("SELECT 1 FROM cms_agenda LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS cms_agenda (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT,
        data_inicio DATETIME NOT NULL,
        data_fim DATETIME NOT NULL,
        cor VARCHAR(20) DEFAULT '#3b82f6',
        status ENUM('aberto', 'confirmado', 'cancelado', 'concluido') DEFAULT 'aberto',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

$custom_title = "Agenda e Compromissos";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Agenda e Compromissos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Planeje publicações, reuniões e eventos de marketing da unidade.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <button class="btn-sq" onclick="resetForm()" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Evento
            </button>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="marketing_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="marketing_campanhas.php" class="tab-item-sq">Campanhas</a>
    <a href="marketing_social_media.php" class="tab-item-sq">Social Media</a>
    <a href="marketing_agenda.php" class="tab-item-sq active">Offline</a>
</div>

    

    <div style="display: grid; grid-template-columns: 1fr 380px; gap: 30px; align-items: start;">
        <!-- Coluna do Calendário -->
        <div class="dashboard-container" style="padding: 30px;">
            <div id='calendar' style="min-height: 700px;"></div>
        </div>

        <!-- Coluna do Formulário (Sidebar Square) -->
        <div class="dashboard-container" id="formSidebar" style="padding: 35px; position: sticky; top: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 id="formTitle" style="font-size: 1.25rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;"><span>Novo Evento</span></h2>
            </div>

            <input type="hidden" id="eventoId">

            <div style="display: grid; gap: 20px;">
                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Título do Evento</label>
                    <input type="text" id="titulo" placeholder="Ex: Campanha Verão 2026" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data/Hora Início</label>
                        <input type="datetime-local" id="start" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                    </div>
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data/Hora Término</label>
                        <input type="datetime-local" id="end" style="width:100%; height: 45px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-sm); font-weight: 600;">
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Cor Indicativa</label>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <input type="color" id="cor" style="width: 50px; height: 40px; border: 1px solid var(--border-color); padding: 2px; cursor: pointer; background: #fff;" value="#3b82f6">
                        <span style="font-size: var(--fs-sm); color: var(--text-dark); font-weight: 700; text-transform: uppercase;">Ajustar tom</span>
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Descrição / Detalhes</label>
                    <textarea id="descricao" placeholder="Anotações internas sobre o compromisso..." style="width:100%; min-height:120px; background: #fafafa; border: 1px solid var(--border-color); padding: 15px; font-size: var(--fs-sm); font-weight: 500; line-height: 1.5;"></textarea>
                </div>

                <div style="display:grid; grid-template-columns: 1fr auto; gap: 10px; margin-top: 10px;">
                    <button onclick="salvarEvento()" class="btn-sq" id="btnSalvar" style="padding: 15px;">SALVAR EVENTO</button>
                    <button onclick="deletarEvento()" id="btnDeletar" style="display:none; width: 50px; height: 50px; border: 1px solid #ef4444; background: #fff; color: #ef4444; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Excluir">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Custom FullCalendar Styling to match Square Aesthetic */
    :root {
        --fc-border-color: #e2e8f0;
        --fc-today-bg-color: #f8fafc;
        --fc-button-hover-bg-color: #f1f5f9;
        --fc-button-active-bg-color: #08153a;
        --fc-event-selected-overlay-color: rgba(0,0,0,0.1);
    }
    
    .fc { font-family: 'Inter', sans-serif; }
    .fc .fc-toolbar-title { font-size: 1.1rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dark); }
    
    .fc .fc-button-primary {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-dark);
        font-size: var(--fs-xs);
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-radius: 0;
        padding: 8px 15px;
    }
    
    .fc .fc-button-primary:hover { background: #f8fafc; border-color: #cbd5e1; color: #08153a; }
    .fc .fc-button-primary:not(:disabled):active, 
    .fc .fc-button-primary:not(:disabled).fc-button-active {
        background: #08153a;
        border-color: #08153a;
        color: #fff;
    }

    .fc th { font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; padding: 12px 0; color: var(--text-muted); background: #f8fafc; border: 1px solid var(--border-color); }
    .fc-daygrid-day-number { font-size: var(--fs-sm); font-weight: 800; color: #94a3b8; padding: 10px !important; }
    .fc-day-today .fc-daygrid-day-number { color: var(--primary-green); font-size: var(--fs-base); font-weight: 900; }
    
    .fc-event { border-radius: 0; border: none; padding: 4px 8px; font-size: var(--fs-xs); font-weight: 700; cursor: move; }
    
    #btnDeletar:hover { background: #fee2e2 !important; }
</style>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales/pt-br.js'></script>

<script>
    var calendar;
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            editable: true,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: 'api_agenda_eventos.php',
            dateClick: function (info) {
                populateForm(info.dateStr + 'T08:00');
            },
            eventClick: function (info) {
                populateForm(null, info.event);
            },
            eventDrop: function (info) {
                atualizarEventoDrop(info.event);
            }
        });
        calendar.render();
    });

    function populateForm(dataStr = null, event = null) {
        if (event) {
            document.getElementById('formTitle').querySelector('span').innerText = 'Editar Evento';
            document.getElementById('eventoId').value = event.id;
            document.getElementById('titulo').value = event.title;
            document.getElementById('start').value = formatDateTime(event.start);
            document.getElementById('end').value = formatDateTime(event.end || event.start);
            document.getElementById('descricao').value = event.extendedProps.descricao || '';
            document.getElementById('cor').value = event.backgroundColor || '#3b82f6';
            document.getElementById('btnDeletar').style.display = 'flex';
            document.getElementById('btnDeletar').style.alignItems = 'center';
            document.getElementById('btnDeletar').style.justifyContent = 'center';
        } else {
            document.getElementById('formTitle').querySelector('span').innerText = 'Novo Evento';
            document.getElementById('eventoId').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('start').value = dataStr || '';
            document.getElementById('end').value = dataStr || '';
            document.getElementById('descricao').value = '';
            document.getElementById('cor').value = '#3b82f6';
            document.getElementById('btnDeletar').style.display = 'none';
        }
    }

    function resetForm() {
        populateForm();
    }

    function formatDateTime(date) {
        if (!date) return '';
        const d = new Date(date);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    }

    function salvarEvento() {
        const tituloValue = document.getElementById('titulo').value;
        if (!tituloValue) {
            alert('Por favor, informe um título para o evento.');
            return;
        }

        const data = {
            id: document.getElementById('eventoId').value,
            titulo: tituloValue,
            start: document.getElementById('start').value,
            end: document.getElementById('end').value,
            descricao: document.getElementById('descricao').value,
            cor: document.getElementById('cor').value,
            acao: 'salvar'
        };

        fetch('api_agenda_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    calendar.refetchEvents();
                    resetForm();
                } else alert(res.message);
            });
    }

    function deletarEvento() {
        if (!confirm('Excluir este evento?')) return;
        const data = {
            id: document.getElementById('eventoId').value,
            acao: 'deletar'
        };

        fetch('api_agenda_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    calendar.refetchEvents();
                    resetForm();
                } else alert(res.message);
            });
    }

    function atualizarEventoDrop(event) {
        const data = {
            id: event.id,
            titulo: event.title,
            start: formatDateTime(event.start),
            end: formatDateTime(event.end || event.start),
            descricao: event.extendedProps.descricao,
            cor: event.backgroundColor,
            acao: 'salvar'
        };

        fetch('api_agenda_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json());
    }
</script>

<?php include 'footer.php'; ?>