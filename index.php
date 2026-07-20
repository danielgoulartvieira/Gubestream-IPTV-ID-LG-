<?php
// Ativa a exibição de erros para diagnóstico caso o servidor mude algo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$cacheFile = 'cache_listas.json';
$pastaList = 'list';
$tempoCache = 300; // 5 minutos

if (!file_exists($cacheFile) || (time() - filemtime($cacheFile) > $tempoCache)) {
    $listasAgrupadas = [];

    if (is_dir($pastaList)) {
        $arquivos = scandir($pastaList);
        foreach ($arquivos as $arquivo) {
            if (pathinfo($arquivo, PATHINFO_EXTENSION) === 'm3u8') {
                $caminhoCompleto = $pastaList . '/' . $arquivo;
                $handle = @fopen($caminhoCompleto, "r");
                if ($handle) {
                    $nomeLista = pathinfo($arquivo, PATHINFO_FILENAME);
                    $listasAgrupadas[$nomeLista] = [];
                    $infoCanal = null;

                    while (($linha = fgets($handle)) !== false) {
                        $linha = trim($linha);
                        if (strpos($linha, '#EXTINF:') === 0) {
                            $partesVirgula = explode(',', $linha);
                            $nome = end($partesVirgula);

                            $logo = '';
                            if (preg_match('/tvg-logo="([^"]+)"/', $linha, $matches)) {
                                $logo = $matches[1];
                            }

                            $infoCanal = [
                                'nome' => trim($nome),
                                'logo' => trim($logo)
                            ];
                        } elseif (!empty($linha) && strpos($linha, '#') !== 0 && $infoCanal) {
                            $infoCanal['url'] = $linha;
                            $listasAgrupadas[$nomeLista][] = $infoCanal;
                            $infoCanal = null;
                        }
                    }
                    fclose($handle);
                }
            }
        }
        file_put_contents($cacheFile, json_encode($listasAgrupadas));
    }
}

$todasAsListas = json_decode(@file_get_contents($cacheFile), true) ?: [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GubeStream Player</title>
    <link rel="shortcut icon" type="image/svg" href="favicon.svg"/>
    <link rel="manifest" href="manifest.json">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        :root {
            --bg-color: #111;
            --text-color: #fff;
            --accent-color: #007bff;
            --card-bg: #222;
            --input-bg: #1e1e1e;
            --input-border: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
        }

        .player-container {
            max-width: 960px;
            margin: 0 auto 30px auto;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        video {
            width: 100%;
            display: block;
            aspect-ratio: 16/9;
        }

        .titulo-player {
            padding: 12px;
            background: #161616;
            margin: 0;
            font-size: 1.2rem;
            border-top: 1px solid #333;
        }

        /* Container da Barra de Busca */
        .busca-container {
            max-width: 960px;
            margin: 0 auto 25px auto;
        }

        .busca-input {
            width: 100%;
            padding: 12px 20px;
            font-size: 1rem;
            background-color: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 30px;
            color: var(--text-color);
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .busca-input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.5);
        }

        .listas-container {
            max-width: 960px;
            margin: 0 auto;
        }

        .linha-lista {
            margin-bottom: 35px;
        }

        .titulo-linha {
            font-size: 1.3rem;
            margin-bottom: 15px;
            text-transform: capitalize;
            border-left: 4px solid var(--accent-color);
            padding-left: 10px;
        }

        /* Grid Responsivo */
        .grid-canais {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            padding: 5px 0;
        }

        .card-canal {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, border-color 0.2s;
            border: 2px solid transparent;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-canal:hover {
            transform: scale(1.05);
            border-color: var(--accent-color);
        }

        .logo-canal {
            width: 100%;
            height: 90px;
            object-fit: contain;
            margin-bottom: 8px;
            background: #666;
            border-radius: 4px;
        }

        .logo-placeholder {
            width: 100%;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #666;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .logo-placeholder img {
            max-width: 60%;
            max-height: 60%;
            object-fit: contain;
        }

        .nome-canal {
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: auto;
        }
    </style>
</head>
<body>

    <div class="player-container">
        <video id="video-player" controls></video>
        <h2 class="titulo-player" id="canal-atual">Selecione um canal abaixo</h2>
    </div>

    <!-- Barra de busca por cima das listas -->
    <div class="busca-container">
        <input type="text" id="busca-canal" class="busca-input" placeholder="Digite o nome do canal para buscar...">
    </div>

    <div class="listas-container">
        <?php if (empty($todasAsListas)): ?>
            <p style="text-align: center; color: #888;">Nenhum canal encontrado na pasta 'list/'. Certifique-se de que os arquivos .m3u8 estão lá.</p>
        <?php else: ?>
            <?php foreach ($todasAsListas as $nomeLista => $canais): ?>
                <?php if (empty($canais)) continue; ?>
                
                <div class="linha-lista">
                    <div class="titulo-linha"><?php echo htmlspecialchars(str_replace(['-', '_'], ' ', $nomeLista)); ?></div>
                    <div class="grid-canais">
                        
                        <?php foreach ($canais as $canal): ?>
                            <div class="card-canal" 
                                 data-url="<?php echo htmlspecialchars($canal['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 data-nome="<?php echo htmlspecialchars($canal['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                 onclick="ouvirCliqueCanal(this)">
                                
                                <?php if (!empty($canal['logo'])): ?>
                                    <img class="logo-canal" src="<?php echo htmlspecialchars($canal['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="logo-placeholder" style="display:none;">
                                        <img src="logo.svg" alt="Canal">
                                    </div>
                                <?php else: ?>
                                    <div class="logo-placeholder">
                                        <img src="logo.svg" alt="Canal">
                                    </div>
                                <?php endif; ?>

                                <div class="nome-canal" title="<?php echo htmlspecialchars($canal['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($canal['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    const video = document.getElementById('video-player');
    const tituloAtual = document.getElementById('canal-atual');
    let hls = null;

    function ouvirCliqueCanal(elemento) {
        const url = elemento.getAttribute('data-url');
        const nome = elemento.getAttribute('data-nome');
        playerPlay(url, nome);
    }

    function playerPlay(url, nome) {
        if (url.startsWith('rtmp://')) {
            alert('O protocolo RTMP não é suportado pelos navegadores modernos. Tente um link HTTP/HTTPS.');
            return;
        }

        tituloAtual.textContent = "Transmitindo: " + nome;
        window.scrollTo({ top: 0, behavior: 'smooth' });

        if (Hls.isSupported()) {
            if (hls) hls.destroy();

            hls = new Hls({
                maxBufferLength: 30,
                maxMaxBufferLength: 600,
                enableWorker: true,
                lowLatencyMode: true,
                xhrSetup: function (xhr, url) {
                    xhr.withCredentials = false; 
                }
            });

            hls.loadSource(url);
            hls.attachMedia(video);
            
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(e => console.log("Autoplay bloqueado pelo usuário."));
            });

            hls.on(Hls.Events.ERROR, function (event, data) {
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            console.error("Erro de Rede (provável bloqueio de CORS ou link offline):", data);
                            hls.startLoad();
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            console.error("Erro de Mídia, tentando recuperar...", data);
                            hls.recoverMediaError();
                            break;
                        default:
                            hls.destroy();
                            break;
                    }
                }
            });

        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = url;
            video.addEventListener('loadedmetadata', function() {
                video.play();
            });
        }
    }

    // Lógica da Barra de Busca Simplificada e Corrigida
    document.getElementById('busca-canal').addEventListener('input', function() {
        const termoBusca = this.value.toLowerCase().trim();
        const linhasLista = document.querySelectorAll('.linha-lista');

        linhasLista.forEach(linha => {
            const cards = inline = linha.querySelectorAll('.card-canal');
            let canaisVisiveisNaLinha = 0;

            cards.forEach(card => {
                const nomeCanalAttr = card.getAttribute('data-nome') || '';
                const nomeCanalText = card.querySelector('.nome-canal') ? card.querySelector('.nome-canal').textContent : '';
                
                const nomeFinal = (nomeCanalAttr + ' ' + nomeCanalText).toLowerCase();
                
                if (nomeFinal.includes(termoBusca)) {
                    card.style.display = '';
                    canaisVisiveisNaLinha++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (canaisVisiveisNaLinha > 0) {
                linha.style.display = '';
            } else {
                linha.style.display = 'none';
            }
        });
    });
</script>
<!-- Pop-up de Instalação Customizado -->
<div id="pwa-install-pop" class="pwa-box">
    <div class="pwa-content">
        <img src="logo-app.svg" alt="Logo CUBE IPTV" class="pwa-logo">
        <div class="pwa-text">
            <h3>Instalar CUBE IPTV</h3>
            <p>Adicione o app à sua tela inicial para acessar mais rápido e com melhor desempenho.</p>
        </div>
    </div>
    <div class="pwa-buttons">
        <button id="pwa-btn-cancel" class="pwa-btn btn-secundario">Agora não</button>
        <button id="pwa-btn-install" class="pwa-btn btn-primario">Instalar</button>
    </div>
</div>

<style>
    /* Estilização do Pop-up com Bordas Arredondadas */
    .pwa-box {
        position: fixed;
        bottom: -150px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 420px;
        background-color: #222;
        border: 1px solid #333;
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        z-index: 9999;
        transition: bottom 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97);
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .pwa-box.mostrar {
        bottom: 20px;
    }

    .pwa-content {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 14px;
    }

    .pwa-logo {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        object-fit: contain;
        background-color: #fff;
        padding: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .pwa-text h3 {
        margin: 0 0 4px 0;
        color: #fff;
        font-size: 1.1rem;
    }

    .pwa-text p {
        margin: 0;
        color: #aaa;
        font-size: 0.85rem;
        line-height: 1.3;
    }

    .pwa-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .pwa-btn {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: bold;
        cursor: pointer;
        border: none;
        outline: none;
        transition: background 0.2s;
    }

    .btn-primario {
        background-color: #007bff;
        color: #fff;
    }

    .btn-primario:hover {
        background-color: #0056b3;
    }

    .btn-secundario {
        background-color: transparent;
        color: #aaa;
    }

    .btn-secundario:hover {
        background-color: rgba(255,255,255,0.05);
        color: #fff;
    }
</style>

<script>
    // Registro do Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Service Worker ativo com sucesso!', reg.scope))
                .catch(err => console.error('Falha ao registrar Service Worker:', err));
        });
    }

    // Lógica de captura do evento nativo de instalação
    let eventoInstalacao;
    const popUp = document.getElementById('pwa-install-pop');
    const btnInstalar = document.getElementById('pwa-btn-install');
    const btnCancelar = document.getElementById('pwa-btn-cancel');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Impede que o navegador mostre o aviso padrão imediatamente
        e.preventDefault();
        // Guarda o evento para ser executado depois
        eventoInstalacao = e;
        
        // Verifica se o usuário já rejeitou nesta sessão para não ficar irritante
        if (!sessionStorage.getItem('pwa-recusado')) {
            // Mostra o nosso pop-up customizado com delay sutil
            setTimeout(() => {
                popUp.classList.add('mostrar');
            }, 2000);
        }
    });

    btnInstalar.addEventListener('click', async () => {
        if (!eventoInstalacao) return;
        
        // Oculta o pop-up da tela
        popUp.classList.remove('mostrar');
        // Aciona o prompt de instalação nativo
        eventoInstalacao.prompt();
        
        // Aguarda a resposta do usuário (Instalou ou Cancelou)
        const { outcome } = await eventoInstalacao.userChoice;
        console.log(`Escolha do usuário: ${outcome}`);
        
        // Limpa o evento
        eventoInstalacao = null;
    });

    btnCancelar.addEventListener('click', () => {
        popUp.classList.remove('mostrar');
        // Salva na sessão para não incomodar até fechar a aba
        sessionStorage.setItem('pwa-recusado', 'true');
    });

    // Oculta o pop-up se o app já tiver sido instalado
    window.addEventListener('appinstalled', () => {
        popUp.classList.remove('mostrar');
        console.log('CUBE IPTV instalado com sucesso!');
    });
</script>
</body>
</html>