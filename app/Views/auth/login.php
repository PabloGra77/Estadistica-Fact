<?php
/**
 * Página de login — sin layout principal (sin sidebar).
 */
$errorMsg ??= null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Acceso — PPL IPS Goleman</title>
    <meta name="robots" content="noindex,nofollow"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; }

        .lw { min-height: 100vh; display: flex; }

        /* Panel izquierdo */
        .lw-brand {
            display: none;
            position: relative; overflow: hidden;
            flex-direction: column; justify-content: center; padding: 3.5rem;
            background: linear-gradient(155deg, #060e2b 0%, #0d2660 45%, #1a0f3c 100%);
            color: #fff;
        }
        @media (min-width: 768px) { .lw-brand { display: flex; width: 44%; } }
        .lw-brand::before {
            content: ''; position: absolute; top: -80px; right: -80px;
            width: 320px; height: 320px; border-radius: 50%;
            background: radial-gradient(circle, rgba(99,179,237,.18) 0%, transparent 70%);
        }
        .lw-brand::after {
            content: ''; position: absolute; bottom: -60px; left: -60px;
            width: 260px; height: 260px; border-radius: 50%;
            background: radial-gradient(circle, rgba(167,139,250,.15) 0%, transparent 70%);
        }
        .deco-ring { position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,.07); }
        .dr1 { width: 180px; height: 180px; top: 10%; left: 58%; }
        .dr2 { width:  90px; height:  90px; bottom: 18%; right: 5%; }
        .dr3 { width:  50px; height:  50px; top: 42%; left: 8%; }
        .lb-badge {
            display: inline-block; font-size: .63rem; font-weight: 700;
            letter-spacing: .25em; text-transform: uppercase;
            color: rgba(255,255,255,.45); border: 1px solid rgba(255,255,255,.15);
            padding: .25rem .65rem; border-radius: 20px; margin-bottom: 1.4rem;
        }
        .lb-ppl {
            font-size: 5.5rem; font-weight: 900; letter-spacing: .08em; line-height: 1;
            background: linear-gradient(135deg, #fff 30%, rgba(147,197,253,.8));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; margin-bottom: .4rem;
        }
        .lb-title { font-size: 1.05rem; font-weight: 600; color: rgba(255,255,255,.9); margin-bottom: .25rem; }
        .lb-sub   { font-size: .8rem; color: rgba(255,255,255,.5); line-height: 1.6; }
        .lb-divider { width: 42px; height: 3px; border-radius: 2px;
            background: linear-gradient(90deg, #63b3ed, #7c3aed); margin: 1.5rem 0; }
        .lb-roles { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .65rem; }
        .lb-role {
            display: flex; align-items: center; gap: .75rem; padding: .6rem .85rem;
            border-radius: 12px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
        }
        .lb-role-icon { width: 34px; height: 34px; border-radius: 9px; display: flex;
            align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .lb-role-info strong { display: block; font-size: .8rem; font-weight: 600; color: #fff; }
        .lb-role-info span   { font-size: .7rem; color: rgba(255,255,255,.43); }

        /* Panel derecho */
        .lw-form {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 2.5rem 1.5rem; background: #f0f4fb; position: relative;
        }
        .lw-form::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #0d2660, #7c3aed, #0d2660);
        }
        .lw-mobile-hd { display: block; text-align: center; margin-bottom: 1.5rem; }
        @media (min-width: 768px) { .lw-mobile-hd { display: none; } }
        .lw-mobile-hd .m-ppl { font-size: 2.4rem; font-weight: 900; color: #0d2660; }
        .lw-mobile-hd .m-sub { font-size: .74rem; color: #6c757d; }
        .lw-card {
            width: 100%; max-width: 430px; background: #fff; border-radius: 20px;
            box-shadow: 0 16px 48px rgba(13,47,107,.12), 0 2px 8px rgba(0,0,0,.04);
            padding: 2.5rem 2.25rem 2rem;
            animation: fadeUp .35s ease-out;
        }
        @keyframes fadeUp { from { opacity:0;transform:translateY(12px); } to { opacity:1;transform:translateY(0); } }
        .lw-card-hd { text-align: center; margin-bottom: 2rem; }
        .lw-card-hd .icon-box {
            width: 62px; height: 62px; border-radius: 16px;
            background: linear-gradient(135deg, #0d2660, #7c3aed);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem; font-size: 1.7rem;
            box-shadow: 0 6px 20px rgba(13,38,96,.28);
        }
        .lw-card-hd h2 { font-size: 1.4rem; font-weight: 700; color: #0d2660; margin: 0 0 .25rem; }
        .lw-card-hd p  { font-size: .82rem; color: #8a93a2; margin: 0; }
        .field-g { margin-bottom: 1.1rem; }
        .field-g label { display: block; font-size: .78rem; font-weight: 600; color: #374151; margin-bottom: .35rem; }
        .field-wrap { position: relative; display: flex; align-items: center; }
        .field-icon { position: absolute; left: .875rem; color: #9ca3af; pointer-events: none; z-index: 1; }
        .field-wrap input {
            padding-left: 2.6rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: .9rem; height: 44px; width: 100%;
            transition: border-color .2s, box-shadow .2s; background: #f9fbff;
        }
        .field-wrap input:focus { border-color: #0d2660; box-shadow: 0 0 0 3px rgba(13,38,96,.09); background: #fff; outline: none; }
        .domain-addon {
            height: 44px; display: flex; align-items: center; padding: 0 .75rem;
            background: #eef2fa; border: 1.5px solid #e2e8f0; border-left: none;
            border-radius: 0 10px 10px 0; font-size: .8rem; color: #6c757d; white-space: nowrap; flex-shrink: 0;
        }
        .field-wrap.has-addon input { border-radius: 10px 0 0 10px !important; flex: 1; }
        .pwd-toggle {
            position: absolute; right: .75rem; background: none; border: none;
            padding: 0; color: #9ca3af; cursor: pointer; font-size: 1rem; z-index: 1;
        }
        .pwd-toggle:hover { color: #0d2660; }
        .btn-enter {
            width: 100%; height: 46px; border: none; border-radius: 11px;
            background: linear-gradient(135deg, #0d2660 0%, #1e40af 60%, #7c3aed 100%);
            color: #fff; font-size: .95rem; font-weight: 600; margin-top: .5rem; cursor: pointer;
            transition: opacity .2s, transform .1s; box-shadow: 0 4px 16px rgba(13,38,96,.3);
        }
        .btn-enter:hover { opacity: .92; }
        .btn-enter:active { transform: scale(.98); }
        .err-box {
            display: flex; align-items: center; gap: .6rem; padding: .65rem .9rem;
            border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca;
            color: #dc2626; font-size: .84rem; margin-bottom: .75rem;
        }
        .lw-footer { text-align: center; font-size: .72rem; color: #b0bac9; margin-top: 1.4rem; line-height: 1.6; }
    </style>
</head>
<body>
<div class="lw">

    <!-- Panel izquierdo -->
    <div class="lw-brand">
        <div class="deco-ring dr1"></div>
        <div class="deco-ring dr2"></div>
        <div class="deco-ring dr3"></div>
        <span class="lb-badge">Sistema de Gestión</span>
        <div class="lb-ppl">PPL</div>
        <div class="lb-title">Personas Privadas de la Libertad</div>
        <div class="lb-sub">IPS Goleman Servicio Integral S.A.S.<br>Tablero de Atenciones</div>
        <div class="lb-divider"></div>
        <ul class="lb-roles">
            <li class="lb-role">
                <div class="lb-role-icon" style="background:rgba(96,165,250,.15)">⚙️</div>
                <div class="lb-role-info"><strong>Administrador</strong><span>Acceso total al sistema</span></div>
            </li>
            <li class="lb-role">
                <div class="lb-role-icon" style="background:rgba(52,211,153,.15)">💳</div>
                <div class="lb-role-info"><strong>Facturador</strong><span>Gestión y cargue de facturación</span></div>
            </li>
            <li class="lb-role">
                <div class="lb-role-icon" style="background:rgba(251,191,36,.15)">👥</div>
                <div class="lb-role-info"><strong>Equipo PPL</strong><span>Atención y seguimiento de pacientes</span></div>
            </li>
            <li class="lb-role">
                <div class="lb-role-icon" style="background:rgba(167,139,250,.15)">📊</div>
                <div class="lb-role-info"><strong>Estadístico</strong><span>Análisis de datos y reportes</span></div>
            </li>
        </ul>
    </div>

    <!-- Panel derecho -->
    <div class="lw-form">
        <div class="lw-mobile-hd">
            <div class="m-ppl">PPL</div>
            <div class="m-sub">IPS Goleman · Tablero de Atenciones</div>
        </div>

        <div class="lw-card">
            <div class="lw-card-hd">
                <div class="icon-box">🔐</div>
                <h2>Bienvenido</h2>
                <p>Ingresa con tu correo corporativo IPS Goleman</p>
            </div>

            <form method="post" action="/login" autocomplete="on" novalidate>
                <?= Security::csrfField() ?>

                <?php if ($errorMsg): ?>
                <div class="err-box">⚠️ <?= Security::e($errorMsg) ?></div>
                <?php endif; ?>

                <div class="field-g">
                    <label for="usuario">Correo corporativo</label>
                    <div class="field-wrap has-addon">
                        <span class="field-icon">👤</span>
                        <input id="usuario" name="usuario" type="text"
                               placeholder="nombre.apellido" autocomplete="username"
                               maxlength="100" required/>
                        <span class="domain-addon">@ipsgoleman.com</span>
                    </div>
                </div>

                <div class="field-g">
                    <label for="pwd">Contraseña</label>
                    <div class="field-wrap">
                        <span class="field-icon">🔒</span>
                        <input id="pwd" name="password" type="password"
                               placeholder="••••••••" autocomplete="current-password"
                               style="padding-right:2.75rem" maxlength="128" required/>
                        <button type="button" class="pwd-toggle" id="toggle-pwd">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn-enter">Ingresar al sistema →</button>
            </form>

            <div class="lw-footer">
                Acceso restringido · solo personal autorizado<br>
                <strong>IPS Goleman Servicio Integral S.A.S.</strong>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('toggle-pwd').addEventListener('click', function () {
    const f = document.getElementById('pwd');
    if (f.type === 'password') { f.type = 'text'; this.textContent = '🙈'; }
    else { f.type = 'password'; this.textContent = '👁'; }
});
</script>
</body>
</html>
