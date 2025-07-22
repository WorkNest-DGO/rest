<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);

$title = 'Mesas';
ob_start();
?>
<link href="../../utils/css/style2.css" rel="stylesheet">
<!-- Page Header Start -->


<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Meseros</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Mesas</a>
            </div>
        </div>
    </div>
</div>


<!-- Page Header End -->
<h1>Mesas</h1>
<div>
    <button id="btn-unir">Unir mesas</button>
    <select id="filtro-area"></select>
</div>
<div id="tablero"></div>
<div id="modal-detalle" style="display:none;"></div>



<div class='app'>
    <main class='project'>
        <div class='project-info'>
            <h1>Homepage Design</h1>
            <div class='project-participants'>
                <span></span>
                <span></span>
                <span></span>
                <button class='project-participants__add'>Add Participant</button>

            </div>
        </div>
        <div class='project-tasks'>
            <div class='project-column'>
                <div class='project-column-heading'>
                    <h2 class='project-column-heading__title'>Task Ready</h2><button class='project-column-heading__options'><i class="fas fa-ellipsis-h"></i></button>
                </div>

                <div class='task' draggable='true'>
                    <div class='task__tags'><span class='task__tag task__tag--copyright'>Copywriting</span><button class='task__options'><i class="fas fa-ellipsis-h"></i></button></div>
                    <p>Konsep hero title yang menarik</p>
                    <div class='task__stats'>
                        <span><time datetime="2021-11-24T20:00:00"><i class="fas fa-flag"></i>Nov 24</time></span>
                        <span><i class="fas fa-comment"></i>2</span>
                        <span><i class="fas fa-paperclip"></i>3</span>
                        <span class='task__owner'></span>
                    </div>
                </div>
            </div>
            <div class='project-column'>
                <div class='project-column-heading'>
                    <h2 class='project-column-heading__title'>In Progress</h2><button class='project-column-heading__options'><i class="fas fa-ellipsis-h"></i></button>
                </div>

                <div class='task' draggable='true'>
                    <div class='task__tags'><span class='task__tag task__tag--illustration'>Illustration</span><button class='task__options'><i class="fas fa-ellipsis-h"></i></button></div>
                    <p>Create the landing page graphics for the hero slider.</p>
                    <div class='task__stats'>
                        <span><time datetime="2021-11-24T20:00:00"><i class="fas fa-flag"></i>Nov 24</time></span>
                        <span><i class="fas fa-comment"></i>4</span>
                        <span><i class="fas fa-paperclip"></i>8</span>
                        <span class='task__owner'></span>
                    </div>
                </div>

            </div>
            <div class='project-column'>
                <div class='project-column-heading'>
                    <h2 class='project-column-heading__title'>Needs Review</h2><button class='project-column-heading__options'><i class="fas fa-ellipsis-h"></i></button>
                </div>

                <div class='task' draggable='true'>
                    <div class='task__tags'><span class='task__tag task__tag--illustration'>Illustration</span><button class='task__options'><i class="fas fa-ellipsis-h"></i></button></div>
                    <p>Move that one image 5px down to make Phil Happy.</p>
                    <div class='task__stats'>
                        <span><time datetime="2021-11-24T20:00:00"><i class="fas fa-flag"></i>Nov 24</time></span>
                        <span><i class="fas fa-comment"></i>2</span>
                        <span><i class="fas fa-paperclip"></i>2</span>
                        <span class='task__owner'></span>
                    </div>
                </div>
            </div>
            <div class='project-column'>
                <div class='project-column-heading'>
                    <h2 class='project-column-heading__title'>Done</h2><button class='project-column-heading__options'><i class="fas fa-ellipsis-h"></i></button>
                </div>
                
                <div class='task' draggable='true'>
                    <div class='task__tags'><span class='task__tag task__tag--copyright'>Copywriting</span><button class='task__options'><i class="fas fa-ellipsis-h"></i></button></div>
                    <p>Amend the contract details.</p>
                    <div class='task__stats'>
                        <span><time datetime="2021-11-24T20:00:00"><i class="fas fa-flag"></i>Nov 24</time></span>
                        <span><i class="fas fa-comment"></i>8</span>
                        <span><i class="fas fa-paperclip"></i>16</span>
                        <span class='task__owner'></span>
                    </div>
                </div>

            </div>

        </div>
    </main>
    <aside class='task-details'>
        <div class='tag-progress'>
            <h2>Task Progress</h2>
            <div class='tag-progress'>
                <p>Copywriting <span>3/8</span></p>
                <progress class="progress progress--copyright" max="8" value="3"> 3 </progress>
            </div>
            <div class='tag-progress'>
                <p>Illustration <span>6/10</span></p>
                <progress class="progress progress--illustration" max="10" value="6"> 6 </progress>
            </div>
            <div class='tag-progress'>
                <p>UI Design <span>2/7</span></p>
                <progress class="progress progress--design" max="7" value="2"> 2 </progress>
            </div>
        </div>
        <div class='task-activity'>
            <h2>Recent Activity</h2>
            <ul>
                <li>
                    <span class='task-icon task-icon--attachment'><i class="fas fa-paperclip"></i></span>
                    <b>Andrea </b>uploaded 3 documents
                    <time datetime="2021-11-24T20:00:00">Aug 10</time>
                </li>
                <li>
                    <span class='task-icon task-icon--comment'><i class="fas fa-comment"></i></span>
                    <b>Karen </b> left a comment
                    <time datetime="2021-11-24T20:00:00">Aug 10</time>
                </li>
                <li>
                    <span class='task-icon task-icon--edit'><i class="fas fa-pencil-alt"></i></span>
                    <b>Karen </b>uploaded 3 documents
                    <time datetime="2021-11-24T20:00:00">Aug 11</time>
                </li>
                <li>
                    <span class='task-icon task-icon--attachment'><i class="fas fa-paperclip"></i></span>
                    <b>Andrea </b>uploaded 3 documents
                    <time datetime="2021-11-24T20:00:00">Aug 11</time>
                </li>
                <li>
                    <span class='task-icon task-icon--comment'><i class="fas fa-comment"></i></span>
                    <b>Karen </b> left a comment
                    <time datetime="2021-11-24T20:00:00">Aug 12</time>
                </li>
            </ul>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="mesas2.js"></script>
<script src="mesas.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
