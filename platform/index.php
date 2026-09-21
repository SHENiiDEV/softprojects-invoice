<?php
require_once __DIR__ . '/classes/class-auth.php';

$login_error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['auth_login'] ) ) {
	$username = isset( $_POST['username'] ) ? trim( $_POST['username'] ) : '';
	$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

	if ( SoftProjects_Auth::attempt_login( $username, $password ) ) {
		header( 'Location: index.php' );
		exit;
	} else {
		$login_error = 'Неверное имя пользователя или пароль.';
	}
}

$is_logged_in = SoftProjects_Auth::is_authenticated();
?>
<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SoftProjects Invoice Helper</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
	<script>
		tailwind.config = {
			darkMode: 'class',
			theme: {
				extend: {
					fontFamily: {
						sans: ['"Plus Jakarta Sans"', 'sans-serif'],
						mono: ['"JetBrains Mono"', 'monospace'],
					},
					colors: {
						brand: {
							bg: '#0c0e12',
							card: '#14171f',
							cardBorder: '#232836',
							neon: '#00f09a',
							neonHover: '#00d689',
							neonGlow: 'rgba(0, 240, 154, 0.18)',
							textMuted: '#8b94a7',
						}
					}
				}
			}
		}
	</script>
	<style>
		body {
			background-color: #0c0e12;
			color: #f3f4f6;
			font-family: 'Plus Jakarta Sans', sans-serif;
		}
		.neon-glow {
			box-shadow: 0 0 25px rgba(0, 240, 154, 0.2);
		}
		.custom-scroll::-webkit-scrollbar {
			width: 6px;
			height: 6px;
		}
		.custom-scroll::-webkit-scrollbar-track {
			background: #14171f;
		}
		.custom-scroll::-webkit-scrollbar-thumb {
			background: #2b3242;
			border-radius: 3px;
		}
	</style>
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-brand-neon selection:text-black">

<?php if ( ! $is_logged_in ) : ?>

	<!-- ========================================== -->
	<!-- 🔒 LOGIN SCREEN (SoftProjects Auth Gate) -->
	<!-- ========================================== -->
	<div class="min-h-screen flex items-center justify-center px-4 py-12 relative overflow-hidden">
		<div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-96 h-96 bg-brand-neon/10 rounded-full blur-3xl pointer-events-none"></div>

		<div class="max-w-md w-full bg-brand-card border border-brand-cardBorder rounded-2xl p-8 shadow-2xl relative z-10">
			
			<div class="text-center mb-8">
				<div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-neon to-emerald-600 flex items-center justify-center font-black text-black text-2xl mx-auto mb-4 shadow-xl shadow-brand-neon/25">
					⚡
				</div>
				<h1 class="text-2xl font-black text-white tracking-tight">SoftProjects</h1>
				<div class="text-xs uppercase tracking-widest text-brand-neon font-bold mt-0.5">Invoice Helper</div>
				<p class="text-xs text-brand-textMuted mt-2">Введите учетные данные для доступа к платформе</p>
			</div>

			<?php if ( ! empty( $login_error ) ) : ?>
			<div class="mb-5 p-3.5 rounded-xl bg-rose-950/60 border border-rose-800/60 text-xs text-rose-300 flex items-center gap-2">
				<svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
				<span><?php echo htmlspecialchars( $login_error, ENT_QUOTES, 'UTF-8' ); ?></span>
			</div>
			<?php endif; ?>

			<form method="POST" action="index.php" class="space-y-4">
				<input type="hidden" name="auth_login" value="1">

				<div>
					<label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1.5">
						Логин (Username)
					</label>
					<input type="text" name="username" required autofocus placeholder="SoftProjects" value="<?php echo isset( $_POST['username'] ) ? htmlspecialchars( $_POST['username'], ENT_QUOTES, 'UTF-8' ) : ''; ?>"
						class="w-full bg-[#0c0e12] border border-brand-cardBorder rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon focus:ring-1 focus:ring-brand-neon transition">
				</div>

				<div>
					<label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1.5">
						Пароль (Password)
					</label>
					<input type="password" name="password" required placeholder="••••••••••••"
						class="w-full bg-[#0c0e12] border border-brand-cardBorder rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon focus:ring-1 focus:ring-brand-neon transition font-mono">
				</div>

				<button type="submit" 
					class="w-full mt-2 py-3 px-4 bg-brand-neon hover:bg-brand-neonHover text-black font-extrabold rounded-xl transition duration-200 flex items-center justify-center gap-2 shadow-lg shadow-brand-neon/20 active:scale-[0.99]">
					<span>Войти в систему</span>
					<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
				</button>
			</form>

			<div class="mt-6 pt-4 border-t border-brand-cardBorder/60 text-center text-[11px] text-gray-500">
				Protected Platform • SoftProjects Security
			</div>
		</div>
	</div>

<?php else : ?>

	<!-- ========================================== -->
	<!-- 🚀 AUTHENTICATED DASHBOARD -->
	<!-- ========================================== -->

	<!-- Top Navigation -->
	<header class="border-b border-brand-cardBorder bg-brand-card/90 backdrop-blur sticky top-0 z-40">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
			<div class="flex items-center gap-3">
				<div class="w-9 h-9 rounded-lg bg-gradient-to-br from-brand-neon to-emerald-600 flex items-center justify-center font-black text-black text-lg shadow-lg shadow-brand-neon/20">
					⚡
				</div>
				<div>
					<div class="font-extrabold text-lg tracking-tight text-white flex items-center gap-2">
						SoftProjects <span class="text-xs uppercase px-2 py-0.5 rounded font-bold bg-brand-neon/10 text-brand-neon border border-brand-neon/30">Invoice Helper</span>
					</div>
					<div class="text-[11px] text-brand-textMuted tracking-wide">Универсальный бот подбора товаров, копирования и генерации инвойсов</div>
				</div>
			</div>

			<div class="flex items-center gap-3">
				<button onclick="refreshSavedShops()" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-brand-cardBorder text-gray-300 hover:text-white hover:border-gray-500 transition flex items-center gap-1.5">
					<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.03 0 01-15.357-2m15.357 2H15"></path></svg>
					Магазины (<span id="saved-shops-badge">0</span>)
				</button>
				
				<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-950 text-emerald-400 border border-emerald-800/50">
					<span class="w-2 h-2 rounded-full bg-brand-neon animate-pulse"></span>
					SoftProjects
				</span>

				<a href="api.php?action=logout&redirect=1" title="Выйти из системы"
					class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-brand-cardBorder bg-[#0c0e12] text-gray-400 hover:text-rose-400 hover:border-rose-900/60 transition flex items-center gap-1">
					<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
					<span>Выйти</span>
				</a>
			</div>
		</div>
	</header>

	<!-- Main Workspace -->
	<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full grid grid-cols-1 lg:grid-cols-12 gap-8">
		
		<!-- Left Panel: Input Form (5 cols) -->
		<section class="lg:col-span-5 flex flex-col gap-6">
			<div class="bg-brand-card border border-brand-cardBorder rounded-2xl p-6 shadow-xl relative overflow-hidden">
				<div class="absolute -top-12 -right-12 w-32 h-32 bg-brand-neon/5 rounded-full blur-2xl pointer-events-none"></div>

				<div class="flex items-center justify-between mb-4">
					<h2 class="text-lg font-bold text-white flex items-center gap-2">
						<svg class="w-5 h-5 text-brand-neon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
						Параметры подбора
					</h2>
					<button type="button" onclick="randomizeOrderId()" title="Сгенерировать случайный Order ID" class="text-xs text-brand-neon hover:underline flex items-center gap-1">
						<span>🎲 Новый #ID</span>
					</button>
				</div>

				<form id="matchForm" onsubmit="handleMatchSubmit(event)" class="space-y-4">
					
					<!-- Store URL -->
					<div>
						<label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1.5">
							Ссылка на каталог магазина <span class="text-brand-neon">*</span>
						</label>
						<div class="relative">
							<input type="url" id="storeUrl" required placeholder="https://drezza.co.uk/shop или каталог любого сайта" 
								class="w-full bg-[#0c0e12] border border-brand-cardBorder rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-brand-neon focus:ring-1 focus:ring-brand-neon transition">
							<button type="button" onclick="toggleSavedShopsDropdown()" title="Выбрать из сохраненных" 
								class="absolute right-2 top-2 px-2 py-1 text-xs bg-brand-cardBorder text-gray-300 rounded-md hover:text-white hover:bg-gray-700">
								▼
							</button>
						</div>
						<!-- Dropdown saved stores -->
						<div id="savedShopsDropdown" class="hidden absolute z-30 mt-1 w-80 bg-brand-card border border-brand-cardBorder rounded-xl shadow-2xl overflow-hidden max-h-56 overflow-y-auto custom-scroll">
							<div id="savedShopsList" class="divide-y divide-brand-cardBorder/50 text-xs"></div>
						</div>
					</div>

					<!-- Target Amount & Currency -->
					<div class="grid grid-cols-3 gap-3">
						<div class="col-span-2">
							<label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1.5">
								Сумма транзакции <span class="text-brand-neon">*</span>
							</label>
							<div class="relative">
								<input type="text" id="targetAmount" required placeholder="200.00" 
									class="w-full bg-[#0c0e12] border border-brand-cardBorder rounded-xl px-4 py-2.5 text-sm font-semibold text-white placeholder-gray-500 focus:outline-none focus:border-brand-neon focus:ring-1 focus:ring-brand-neon transition">
							</div>
						</div>
						<div>
							<label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1.5">
								Валюта
							</label>
							<select id="targetCurrency" class="w-full bg-[#0c0e12] border border-brand-cardBorder rounded-xl px-3 py-2.5 text-sm font-semibold text-white focus:outline-none focus:border-brand-neon transition">
								<option value="EUR" selected>EUR (€)</option>
								<option value="GBP">GBP (£)</option>
								<option value="USD">USD ($)</option>
							</select>
						</div>
					</div>

					<!-- Shipping Configuration -->
					<div class="border border-brand-cardBorder rounded-xl bg-[#0c0e12]/60 p-3.5 space-y-2.5">
						<div class="flex items-center justify-between text-xs font-bold text-gray-300 uppercase tracking-wider">
							<span>Доставка (Shipping)</span>
							<span id="shippingCurrencyLabel" class="text-[11px] text-brand-neon font-normal">в EUR</span>
						</div>

						<div class="grid grid-cols-3 gap-2 text-xs">
							<label class="flex items-center gap-1.5 px-2.5 py-2 rounded-lg bg-[#14171f] border border-brand-cardBorder cursor-pointer hover:border-brand-neon/60 transition has-[:checked]:border-brand-neon has-[:checked]:bg-brand-neon/10">
								<input type="radio" name="shippingMode" value="auto" checked onchange="updateShippingModeUI()" class="text-brand-neon focus:ring-0">
								<span class="text-[11px] text-gray-200">⚡ Авто</span>
							</label>
							<label class="flex items-center gap-1.5 px-2.5 py-2 rounded-lg bg-[#14171f] border border-brand-cardBorder cursor-pointer hover:border-brand-neon/60 transition has-[:checked]:border-brand-neon has-[:checked]:bg-brand-neon/10">
								<input type="radio" name="shippingMode" value="free" onchange="updateShippingModeUI()" class="text-brand-neon focus:ring-0">
								<span class="text-[11px] text-gray-200">🚚 0.00 Free</span>
							</label>
							<label class="flex items-center gap-1.5 px-2.5 py-2 rounded-lg bg-[#14171f] border border-brand-cardBorder cursor-pointer hover:border-brand-neon/60 transition has-[:checked]:border-brand-neon has-[:checked]:bg-brand-neon/10">
								<input type="radio" name="shippingMode" value="manual" onchange="updateShippingModeUI()" class="text-brand-neon focus:ring-0">
								<span class="text-[11px] text-gray-200">✏️ Ручная</span>
							</label>
						</div>

						<!-- Custom Shipping Amount & Title Inputs (visible when manual) -->
						<div id="manualShippingContainer" class="hidden grid grid-cols-2 gap-2.5 pt-1">
							<div>
								<label class="block text-[11px] text-gray-400 mb-1">Сумма доставки</label>
								<div class="relative">
									<input type="text" id="manualShippingCost" placeholder="11.69" value="11.69"
										class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon font-mono">
								</div>
							</div>
							<div>
								<label class="block text-[11px] text-gray-400 mb-1">Название метода</label>
								<input type="text" id="manualShippingTitle" placeholder="Flat rate" value="Flat rate"
									class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
							</div>
						</div>
					</div>

					<!-- Customer / Billing Data Accordion -->
					<div class="border border-brand-cardBorder rounded-xl bg-[#0c0e12]/60 p-4 space-y-3">
						<div class="flex items-center justify-between text-xs font-bold text-gray-300 uppercase tracking-wider">
							<span>Данные покупателя & Биллинг</span>
							<span class="text-[11px] text-brand-neon font-normal">для инвойса</span>
						</div>

						<div class="grid grid-cols-2 gap-2.5">
							<div>
								<label class="block text-[11px] text-gray-400 mb-1">Card Pan</label>
								<input type="text" id="cardPan" placeholder="433467***4469" value="433467***4469"
									class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
							</div>
							<div>
								<label class="block text-[11px] text-gray-400 mb-1">Номер заказа</label>
								<input type="text" id="orderId" placeholder="#DRZ-37708" value="#DRZ-37708"
									class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
							</div>
						</div>

						<div>
							<label class="block text-[11px] text-gray-400 mb-1">ФИО клиента</label>
							<input type="text" id="custName" placeholder="David Jurado Giles" value="David Jurado Giles"
								class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
						</div>

						<div class="grid grid-cols-2 gap-2.5">
							<div>
								<label class="block text-[11px] text-gray-400 mb-1">Email</label>
								<input type="email" id="custEmail" placeholder="info.imprenta@gmail.com" value="info.imprentajurado@gmail.com"
									class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
							</div>
							<div>
								<label class="block text-[11px] text-gray-400 mb-1">Телефон</label>
								<input type="text" id="custPhone" placeholder="34 626829761" value="34 626829761"
									class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
							</div>
						</div>

						<div>
							<label class="block text-[11px] text-gray-400 mb-1">Адрес (Улица, город, страна, индекс)</label>
							<input type="text" id="custAddress" placeholder="Carrer dels Espardenyers, 25, Valls, 43800, Spain" value="Carrer dels Espardenyers, 25, Valls, 43800, Spain"
								class="w-full bg-[#14171f] border border-brand-cardBorder rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-brand-neon">
						</div>
					</div>

					<!-- Options: Force Refresh -->
					<div class="flex items-center justify-between text-xs pt-1">
						<label class="flex items-center gap-2 cursor-pointer text-gray-400 hover:text-gray-300">
							<input type="checkbox" id="forceRefresh" class="rounded bg-[#0c0e12] border-brand-cardBorder text-brand-neon focus:ring-0">
							<span>Принудительно обновить каталог сайта</span>
						</label>
					</div>

					<!-- Submit Buttons -->
					<div class="flex gap-2">
						<button type="submit" id="btnSubmit" 
							class="flex-1 py-3 px-4 bg-brand-neon hover:bg-brand-neonHover text-black font-extrabold rounded-xl transition duration-200 flex items-center justify-center gap-2 shadow-lg shadow-brand-neon/20 active:scale-[0.99]">
							<span id="btnText">⚡ Собрать товары под сумму</span>
							<div id="btnSpinner" class="hidden w-5 h-5 border-2 border-black border-t-transparent rounded-full animate-spin"></div>
						</button>
					</div>

				</form>
			</div>

			<!-- Quick Presets -->
			<div class="bg-brand-card/60 border border-brand-cardBorder/70 rounded-xl p-4">
				<div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Быстрый выбор сумм</div>
				<div class="flex flex-wrap gap-2">
					<button onclick="setAmount(20.00)" class="px-2.5 py-1 rounded bg-brand-card border border-brand-cardBorder text-xs text-gray-300 hover:border-brand-neon hover:text-brand-neon">20.00 €</button>
					<button onclick="setAmount(45.00)" class="px-2.5 py-1 rounded bg-brand-card border border-brand-cardBorder text-xs text-gray-300 hover:border-brand-neon hover:text-brand-neon">45.00 €</button>
					<button onclick="setAmount(200.00)" class="px-2.5 py-1 rounded bg-brand-card border border-brand-cardBorder text-xs text-gray-300 hover:border-brand-neon hover:text-brand-neon">200.00 €</button>
					<button onclick="setAmount(300.00)" class="px-2.5 py-1 rounded bg-brand-card border border-brand-cardBorder text-xs text-gray-300 hover:border-brand-neon hover:text-brand-neon">300.00 €</button>
					<button onclick="setAmount(542.00)" class="px-2.5 py-1 rounded bg-brand-card border border-brand-cardBorder text-xs text-gray-300 hover:border-brand-neon hover:text-brand-neon">542.00 €</button>
					<button onclick="setAmount(998.00)" class="px-2.5 py-1 rounded bg-brand-card border border-brand-cardBorder text-xs text-gray-300 hover:border-brand-neon hover:text-brand-neon">998.00 €</button>
				</div>
			</div>
		</section>

		<!-- Right Panel: Results & Outputs (7 cols) -->
		<section class="lg:col-span-7 flex flex-col gap-6">

			<!-- State: Placeholder / Empty -->
			<div id="emptyState" class="bg-brand-card border border-dashed border-brand-cardBorder rounded-2xl p-12 text-center flex flex-col items-center justify-center min-h-[460px]">
				<div class="w-16 h-16 rounded-2xl bg-[#0c0e12] border border-brand-cardBorder flex items-center justify-center text-3xl mb-4">
					🛒
				</div>
				<h3 class="text-base font-bold text-white mb-1">Ожидание параметров</h3>
				<p class="text-xs text-brand-textMuted max-w-sm">
					Укажите ссылку на каталог магазина и требуемую сумму транзакции. SoftProjects Invoice Helper просканирует сайт и составит точный набор товаров.
				</p>
			</div>

			<!-- State: Results Container -->
			<div id="resultsContainer" class="hidden space-y-6">
				
				<!-- Top Status Summary Card -->
				<div class="bg-brand-card border border-brand-cardBorder rounded-2xl p-6 shadow-xl">
					<div class="flex flex-wrap items-center justify-between gap-4 border-b border-brand-cardBorder pb-4 mb-5">
						<div>
							<div class="text-xs uppercase tracking-wider text-brand-textMuted font-semibold">Магазин</div>
							<div class="flex items-center gap-2">
								<input type="text" id="editStoreName" value="DREZZA" oninput="syncStoreNameChange()"
									class="text-xl font-black text-white tracking-tight bg-transparent border-b border-dashed border-gray-600 focus:border-brand-neon focus:outline-none">
							</div>
							<div id="resStoreMeta" class="text-xs text-brand-neon flex items-center gap-1.5 mt-0.5">
								<span>WooCommerce Store API</span> • <span id="resCatalogCount">12 товаров в каталоге</span>
							</div>
						</div>

						<!-- Action Buttons -->
						<div class="flex flex-wrap items-center gap-2">
							<button onclick="handleMatchSubmit(event)" title="Сгенерировать другой вариант товаров"
								class="px-3 py-2 bg-[#0c0e12] border border-brand-cardBorder hover:border-brand-neon text-gray-200 font-semibold text-xs rounded-xl transition flex items-center gap-1.5 shadow-sm">
								<span>🎲 Пересобрать</span>
							</button>

							<button onclick="openPdfPreviewModal()" 
								class="px-3 py-2 bg-[#0c0e12] border border-brand-cardBorder hover:border-brand-neon text-brand-neon font-semibold text-xs rounded-xl transition flex items-center gap-1.5 shadow-sm">
								<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
								<span>👁️ Предпросмотр</span>
							</button>

							<button onclick="downloadPdfInvoice()" 
								class="px-4 py-2 bg-brand-neon hover:bg-brand-neonHover text-black font-extrabold text-xs rounded-xl transition flex items-center gap-1.5 shadow-md shadow-brand-neon/20">
								<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
								<span>Скачать PDF</span>
							</button>
						</div>
					</div>

					<!-- Metrics Grid -->
					<div class="grid grid-cols-3 gap-4 text-center">
						<div class="bg-[#0c0e12] border border-brand-cardBorder p-3 rounded-xl">
							<div class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Subtotal</div>
							<div id="resSubtotal" class="text-base font-bold text-white mt-0.5">0.00 EUR</div>
						</div>
						<div class="bg-[#0c0e12] border border-brand-cardBorder p-3 rounded-xl">
							<div class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Доставка</div>
							<div id="resShipping" class="text-base font-bold text-brand-neon mt-0.5">0.00 EUR</div>
						</div>
						<div class="bg-[#0c0e12] border border-brand-cardBorder p-3 rounded-xl">
							<div class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Итого (Total)</div>
							<div id="resTotal" class="text-base font-extrabold text-white mt-0.5">0.00 EUR</div>
						</div>
					</div>
				</div>

				<!-- Products Table Breakdown (Interactive, Click-to-Copy & Editable) -->
				<div class="bg-brand-card border border-brand-cardBorder rounded-2xl overflow-hidden shadow-xl">
					<div class="p-4 sm:p-5 border-b border-brand-cardBorder flex flex-wrap items-center justify-between gap-3">
						<div class="flex items-center gap-2">
							<h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
								<span>Товары в корзине</span>
								<span id="resItemsBadge" class="text-[11px] px-2 py-0.5 rounded-full bg-brand-neon/10 text-brand-neon border border-brand-neon/20 font-mono">0 позиций</span>
							</h3>
						</div>

						<!-- Quick Copy Bar for Table -->
						<div class="flex flex-wrap items-center gap-1.5">
							<button onclick="copyAllProductNames()" title="Скопировать все названия товаров построчно"
								class="px-2.5 py-1 rounded-lg bg-[#0c0e12] border border-brand-cardBorder hover:border-brand-neon text-gray-300 hover:text-white text-xs flex items-center gap-1 transition">
								<svg class="w-3.5 h-3.5 text-brand-neon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
								<span>Все названия</span>
							</button>

							<button onclick="copyCompactList()" title="Скопировать нумерованный список с ценами"
								class="px-2.5 py-1 rounded-lg bg-[#0c0e12] border border-brand-cardBorder hover:border-brand-neon text-gray-300 hover:text-white text-xs flex items-center gap-1 transition">
								<span>📝 Список с ценами</span>
							</button>

							<button onclick="copyTsvData()" title="Скопировать таблицу для Excel / Google Sheets"
								class="px-2.5 py-1 rounded-lg bg-[#0c0e12] border border-brand-cardBorder hover:border-brand-neon text-gray-300 hover:text-white text-xs flex items-center gap-1 transition">
								<span>📊 Для Excel/Sheets</span>
							</button>

							<button onclick="addNewCustomRow()" class="px-2.5 py-1 rounded-lg bg-[#0c0e12] border border-brand-cardBorder hover:border-brand-neon text-xs text-brand-neon flex items-center gap-1 transition ml-1">
								<span>➕ Добавить товар</span>
							</button>
						</div>
					</div>

					<div class="overflow-x-auto">
						<table class="w-full text-left text-xs">
							<thead class="bg-[#0c0e12] text-gray-400 font-semibold border-b border-brand-cardBorder uppercase tracking-wider text-[10px]">
								<tr>
									<th class="px-4 py-3">Товар</th>
									<th class="px-3 py-3 text-center w-28">Кол-во</th>
									<th class="px-4 py-3 text-right w-28">Цена за шт.</th>
									<th class="px-4 py-3 text-right w-28">Сумма</th>
									<th class="px-3 py-3 text-center w-20">Действия</th>
								</tr>
							</thead>
							<tbody id="resTableBody" class="divide-y divide-brand-cardBorder/60 text-gray-200">
								<!-- Items rendered dynamically -->
							</tbody>
						</table>
					</div>
				</div>

				<!-- Multi-Format Copy Hub -->
				<div class="bg-brand-card border border-brand-cardBorder rounded-2xl p-6 shadow-xl">
					<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
						<div class="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
							<svg class="w-4 h-4 text-brand-neon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
							Форматы копирования данных
						</div>

						<!-- Copy Tab Switches -->
						<div class="flex flex-wrap gap-1.5 bg-[#0c0e12] p-1 rounded-xl border border-brand-cardBorder text-xs">
							<button onclick="switchCopyTab('receipt')" id="tabBtn-receipt" class="px-2.5 py-1 rounded-lg font-semibold bg-brand-cardBorder text-brand-neon transition">
								📋 Чек
							</button>
							<button onclick="switchCopyTab('compact')" id="tabBtn-compact" class="px-2.5 py-1 rounded-lg font-semibold text-gray-400 hover:text-white transition">
								📝 Список
							</button>
							<button onclick="switchCopyTab('tsv')" id="tabBtn-tsv" class="px-2.5 py-1 rounded-lg font-semibold text-gray-400 hover:text-white transition">
								📊 Excel / Sheets
							</button>
							<button onclick="switchCopyTab('titles')" id="tabBtn-titles" class="px-2.5 py-1 rounded-lg font-semibold text-gray-400 hover:text-white transition">
								🏷️ Названия
							</button>
							<button onclick="switchCopyTab('links')" id="tabBtn-links" class="px-2.5 py-1 rounded-lg font-semibold text-gray-400 hover:text-white transition">
								🔗 Ссылки
							</button>
						</div>
					</div>

					<div class="relative">
						<textarea id="resCopyTextarea" rows="8" readonly
							class="w-full bg-[#0c0e12] border border-brand-cardBorder rounded-xl p-4 font-mono text-xs text-emerald-400/90 overflow-x-auto custom-scroll leading-relaxed focus:outline-none focus:border-brand-neon select-all"></textarea>
						
						<button onclick="copyCurrentTabContent()" id="btnCopyMain"
							class="absolute top-3 right-3 px-3.5 py-1.5 bg-brand-neon hover:bg-brand-neonHover text-black font-extrabold text-xs rounded-lg transition flex items-center gap-1.5 shadow-lg shadow-brand-neon/20">
							<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
							<span id="btnCopyMainLabel">Скопировать</span>
						</button>
					</div>
				</div>

			</div>
		</section>

	</main>

	<!-- ========================================== -->
	<!-- 👁️ LIVE PDF PREVIEW & CUSTOMIZER MODAL -->
	<!-- ========================================== -->
	<div id="pdfModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
		<div class="bg-[#14171f] border border-brand-cardBorder rounded-2xl max-w-5xl w-full h-[90vh] flex flex-col shadow-2xl overflow-hidden">
			
			<!-- Modal Header & Controls -->
			<div class="p-4 border-b border-brand-cardBorder flex flex-wrap items-center justify-between gap-4 bg-[#0c0e12]">
				<div class="flex items-center gap-3">
					<div class="w-8 h-8 rounded-lg bg-brand-neon/10 border border-brand-neon/30 flex items-center justify-center text-brand-neon font-black text-sm">
						PDF
					</div>
					<div>
						<h3 class="text-sm font-bold text-white">Предпросмотр и настройка A4 Инвойса</h3>
						<div class="text-[11px] text-gray-400">Настройте оформление и распечатайте или скачайте готовый PDF</div>
					</div>
				</div>

				<!-- Styling Options Bar -->
				<div class="flex flex-wrap items-center gap-3">
					<!-- Brand Color Picker -->
					<div class="flex items-center gap-1.5 bg-[#14171f] px-2.5 py-1.5 rounded-xl border border-brand-cardBorder text-xs">
						<span class="text-gray-400 text-[11px]">Цвет шапки:</span>
						<button onclick="setBrandColor('#00f09a')" title="Emerald Neon" class="w-5 h-5 rounded-full bg-[#00f09a] border border-white/20 hover:scale-110 transition"></button>
						<button onclick="setBrandColor('#4f46e5')" title="Indigo" class="w-5 h-5 rounded-full bg-[#4f46e5] border border-white/20 hover:scale-110 transition"></button>
						<button onclick="setBrandColor('#e11d48')" title="Crimson" class="w-5 h-5 rounded-full bg-[#e11d48] border border-white/20 hover:scale-110 transition"></button>
						<button onclick="setBrandColor('#eab308')" title="Gold" class="w-5 h-5 rounded-full bg-[#eab308] border border-white/20 hover:scale-110 transition"></button>
						<button onclick="setBrandColor('#0284c7')" title="Sky Blue" class="w-5 h-5 rounded-full bg-[#0284c7] border border-white/20 hover:scale-110 transition"></button>
						<button onclick="setBrandColor('#181a1e')" title="Dark Minimal" class="w-5 h-5 rounded-full bg-[#181a1e] border border-white/40 hover:scale-110 transition"></button>
						<input type="color" id="customColorPicker" value="#00f09a" onchange="setBrandColor(this.value)" class="w-5 h-5 rounded cursor-pointer bg-transparent border-0">
					</div>

					<!-- Payment Note -->
					<div class="flex items-center gap-1.5 bg-[#14171f] px-2.5 py-1.5 rounded-xl border border-brand-cardBorder text-xs">
						<span class="text-gray-400 text-[11px]">Оплата:</span>
						<input type="text" id="editPaymentMethod" value="Direct bank transfer / Online" onchange="refreshPdfPreviewFrame()"
							class="bg-[#0c0e12] border border-brand-cardBorder rounded px-2 py-0.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-brand-neon w-44 truncate">
					</div>

					<!-- Print Button -->
					<button onclick="printPdfFromIframe()" title="Распечатать или сохранить через браузер"
						class="px-3.5 py-1.5 bg-[#14171f] hover:bg-gray-800 border border-brand-cardBorder text-gray-200 font-bold text-xs rounded-xl transition flex items-center gap-1.5">
						<svg class="w-4 h-4 text-brand-neon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
						<span>Печать</span>
					</button>

					<!-- Download PDF Button -->
					<button onclick="downloadPdfInvoice()" 
						class="px-3.5 py-1.5 bg-brand-neon hover:bg-brand-neonHover text-black font-extrabold text-xs rounded-xl transition flex items-center gap-1.5 shadow-md shadow-brand-neon/20">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
						<span>Скачать PDF</span>
					</button>

					<!-- Close Button -->
					<button onclick="closePdfModal()" class="p-1.5 rounded-lg hover:bg-gray-800 text-gray-400 hover:text-white transition">
						✕
					</button>
				</div>
			</div>

			<!-- Modal Body (Iframe) -->
			<div class="flex-1 bg-gray-900 relative">
				<iframe id="pdfPreviewIframe" class="w-full h-full border-0 bg-white"></iframe>
			</div>

		</div>
	</div>

	<!-- Toast Alert -->
	<div id="toast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 transition duration-300 pointer-events-none bg-brand-neon text-black font-bold px-4 py-3 rounded-xl shadow-2xl flex items-center gap-2 text-sm">
		<span id="toastIcon">✓</span>
		<span id="toastMsg">Скопировано в буфер обмена</span>
	</div>

	<!-- JavaScript Client Logic -->
	<script>
		let currentMatchData = null;
		let currentCopyTab = 'receipt';
		let activeBrandColor = '#00f09a';

		document.addEventListener('DOMContentLoaded', () => {
			loadSavedShops();
			
			const storeUrlInput = document.getElementById('storeUrl');
			const orderIdInput = document.getElementById('orderId');

			function updatePrefixFromUrl() {
				const val = storeUrlInput.value.trim();
				try {
					const host = val.replace(/^https?:\/\//i, '').split('/')[0].replace(/^www\./i, '');
					const prefix = host.split('.')[0].substring(0, 3).toUpperCase();
					if (prefix && prefix.length >= 2) {
						if (!orderIdInput.value || /^#[A-Z]{2,4}-\d+$/i.test(orderIdInput.value)) {
							const randNum = Math.floor(10000 + Math.random() * 90000);
							orderIdInput.value = `#${prefix}-${randNum}`;
						}
					}
				} catch (e) {}
			}

			storeUrlInput.addEventListener('input', updatePrefixFromUrl);
			storeUrlInput.addEventListener('change', updatePrefixFromUrl);

			document.getElementById('targetCurrency').addEventListener('change', () => {
				const curr = document.getElementById('targetCurrency').value;
				document.getElementById('shippingCurrencyLabel').textContent = `в ${curr}`;
				const mode = document.querySelector('input[name="shippingMode"]:checked').value;
				if (mode === 'auto' || mode === 'manual') {
					const curVal = document.getElementById('manualShippingCost').value;
					if (curVal === '11.69' || curVal === '9.99' || curVal === '12.80') {
						document.getElementById('manualShippingCost').value = (curr === 'GBP') ? '9.99' : ((curr === 'USD') ? '12.80' : '11.69');
					}
				}
			});
		});

		function randomizeOrderId() {
			const val = document.getElementById('storeUrl').value.trim();
			let prefix = 'DRZ';
			try {
				const host = val.replace(/^https?:\/\//i, '').split('/')[0].replace(/^www\./i, '');
				const pr = host.split('.')[0].substring(0, 3).toUpperCase();
				if (pr && pr.length >= 2) prefix = pr;
			} catch (e) {}
			const randNum = Math.floor(10000 + Math.random() * 90000);
			document.getElementById('orderId').value = `#${prefix}-${randNum}`;
			showToast(`Сгенерирован новый номер заказа: #${prefix}-${randNum}`);
			if (currentMatchData) {
				currentMatchData.customer.order_id = `#${prefix}-${randNum}`;
				triggerRecalculate();
			}
		}

		function syncStoreNameChange() {
			const name = document.getElementById('editStoreName').value.trim();
			if (currentMatchData) {
				currentMatchData.store_name = name || 'STORE';
				triggerRecalculate();
			}
		}

		function updateShippingModeUI() {
			const mode = document.querySelector('input[name="shippingMode"]:checked').value;
			const container = document.getElementById('manualShippingContainer');
			const costInput = document.getElementById('manualShippingCost');
			const titleInput = document.getElementById('manualShippingTitle');
			const curr = document.getElementById('targetCurrency').value;

			if (mode === 'manual') {
				container.classList.remove('hidden');
			} else {
				container.classList.add('hidden');
			}

			if (mode === 'free') {
				costInput.value = '0.00';
				titleInput.value = 'Free shipping';
			} else if (mode === 'auto') {
				const defaultCost = (curr === 'GBP') ? '9.99' : ((curr === 'USD') ? '12.80' : '11.69');
				costInput.value = defaultCost;
				titleInput.value = 'Flat rate';
			}
		}

		function setAmount(val) {
			document.getElementById('targetAmount').value = val.toFixed(2);
		}

		function cleanUrl(str) {
			if (!str) return '';
			str = str.trim();
			const map = {
				'с': 'c', 'С': 'C',
				'а': 'a', 'А': 'A',
				'о': 'o', 'О': 'O',
				'е': 'e', 'Е': 'E',
				'р': 'p', 'Р': 'P',
				'х': 'x', 'Х': 'X',
				'у': 'y', 'У': 'Y',
				'к': 'k', 'К': 'K'
			};
			str = str.replace(/[сСаАоОеЕрРхХуУкК]/g, m => map[m] || m);
			if (!/^https?:\/\//i.test(str)) {
				str = 'https://' + str.replace(/^\/+/, '');
			}
			return str;
		}

		async function handleMatchSubmit(e) {
			if (e && e.preventDefault) e.preventDefault();

			let url = cleanUrl(document.getElementById('storeUrl').value);
			document.getElementById('storeUrl').value = url;
			const amount = document.getElementById('targetAmount').value.trim();
			const currency = document.getElementById('targetCurrency').value;
			const forceRefresh = document.getElementById('forceRefresh').checked;

			const shippingMode = document.querySelector('input[name="shippingMode"]:checked').value;
			const manualShippingCost = document.getElementById('manualShippingCost').value.trim();
			const manualShippingTitle = document.getElementById('manualShippingTitle').value.trim();

			const customer = {
				card_pan: document.getElementById('cardPan').value.trim(),
				order_id: document.getElementById('orderId').value.trim(),
				name: document.getElementById('custName').value.trim(),
				email: document.getElementById('custEmail').value.trim(),
				phone: document.getElementById('custPhone').value.trim(),
				address: document.getElementById('custAddress').value.trim(),
				brand_color: activeBrandColor,
				payment_method: document.getElementById('editPaymentMethod').value.trim(),
				date: new Date().toLocaleDateString('ru-RU')
			};

			const payload = {
				url,
				amount,
				currency,
				force_refresh: forceRefresh,
				customer,
				shipping_mode: shippingMode
			};

			if (shippingMode === 'manual') {
				payload.shipping_cost = manualShippingCost;
				payload.shipping_title = manualShippingTitle;
			} else if (shippingMode === 'free') {
				payload.shipping_cost = 0;
				payload.shipping_title = 'Free shipping';
			}

			setLoading(true);

			try {
				const response = await fetch('api.php?action=match', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload)
				});

				const data = await response.json();

				if (response.status === 401 || data.unauthorized) {
					window.location.reload();
					return;
				}

				if (!response.ok || !data.success) {
					showToast(data.error || 'Ошибка подбора товаров.', true);
					return;
				}

				currentMatchData = data;
				document.getElementById('editStoreName').value = data.store_name;
				renderResults(data);
				loadSavedShops(); // Update shops count
				showToast('Товары успешно подобраны под сумму!');

			} catch (err) {
				showToast('Сетевая ошибка при запросе к серверу.', true);
			} finally {
				setLoading(false);
			}
		}

		function renderResults(data) {
			document.getElementById('emptyState').classList.add('hidden');
			document.getElementById('resultsContainer').classList.remove('hidden');

			document.getElementById('editStoreName').value = data.store_name;
			document.getElementById('resStoreMeta').innerHTML = `<span>${data.catalog_source || 'Каталог'}</span> • <span>${data.catalog_count || data.match.items.length} товаров в базе</span>` + (data.is_cached ? ' • <span class="text-gray-400">из кэша</span>' : '');

			const m = data.match;
			document.getElementById('resSubtotal').textContent = `${Number(m.subtotal).toFixed(2)} ${m.currency}`;
			
			const shipCost = m.shipping.cost > 0 ? `${m.shipping.method_title}: ${Number(m.shipping.cost).toFixed(2)} ${m.currency}` : 'Free shipping (0.00 ' + m.currency + ')';
			document.getElementById('resShipping').textContent = shipCost;
			document.getElementById('resTotal').textContent = `${Number(m.total).toFixed(2)} ${m.currency}`;

			document.getElementById('resItemsBadge').textContent = `${m.items.length} позиций (${m.items.reduce((s, i) => s + Number(i.qty), 0)} шт.)`;

			renderTableRows(m.items, m.currency);
			updateCopyHub(data);
		}

		function renderTableRows(items, currency) {
			const tbody = document.getElementById('resTableBody');
			tbody.innerHTML = '';

			items.forEach((item, index) => {
				const tr = document.createElement('tr');
				tr.className = 'hover:bg-[#181c26] transition group border-b border-brand-cardBorder/40';
				tr.innerHTML = `
					<td class="px-4 py-3">
						<div class="flex items-start justify-between gap-2">
							<div class="flex-1">
								<div class="flex items-center gap-1.5 flex-wrap">
									<span onclick="copySingleItemName(${index})" title="Нажмите, чтобы скопировать название"
										class="text-white font-semibold text-xs hover:text-brand-neon cursor-pointer transition flex items-center gap-1 hover:underline">
										${escapeHtml(item.name)}
									</span>
									<button onclick="copySingleItemName(${index})" title="Скопировать название"
										class="opacity-60 group-hover:opacity-100 text-gray-400 hover:text-brand-neon px-1 py-0.5 rounded text-[11px] hover:bg-gray-800 transition">
										📋
									</button>
								</div>
								${item.link ? `<a href="${escapeHtml(item.link)}" target="_blank" class="text-[10px] text-brand-neon/80 hover:text-brand-neon hover:underline truncate max-w-xs block mt-0.5">Перейти к товару ↗</a>` : ''}
							</div>
							
							<!-- Inline Edit Toggle -->
							<button onclick="promptEditItem(${index})" title="Редактировать название или цену"
								class="opacity-0 group-hover:opacity-100 text-gray-500 hover:text-gray-300 p-1 rounded hover:bg-gray-800 text-[11px] transition">
								✏️
							</button>
						</div>
					</td>
					<td class="px-3 py-3 text-center">
						<div class="inline-flex items-center border border-brand-cardBorder rounded-lg overflow-hidden bg-[#0c0e12]">
							<button onclick="changeItemQty(${index}, -1)" title="Уменьшить" class="px-2 py-0.5 text-gray-400 hover:text-white hover:bg-gray-800 transition font-bold">-</button>
							<span class="w-8 text-center text-white font-bold text-xs py-0.5">${item.qty}</span>
							<button onclick="changeItemQty(${index}, 1)" title="Увеличить" class="px-2 py-0.5 text-gray-400 hover:text-white hover:bg-gray-800 transition font-bold">+</button>
						</div>
					</td>
					<td class="px-4 py-3 text-right font-mono text-gray-300 cursor-pointer hover:text-brand-neon transition" onclick="copyText('${Number(item.unit_price).toFixed(2)} ${currency}')" title="Кликните, чтобы скопировать цену">
						${Number(item.unit_price).toFixed(2)} ${currency}
					</td>
					<td class="px-4 py-3 text-right font-mono font-bold text-white cursor-pointer hover:text-brand-neon transition" onclick="copyText('${Number(item.total).toFixed(2)} ${currency}')" title="Кликните, чтобы скопировать сумму">
						${Number(item.total).toFixed(2)} ${currency}
					</td>
					<td class="px-3 py-3 text-center">
						<div class="flex items-center justify-center gap-1">
							<button onclick="copySingleItemFullLine(${index})" title="Скопировать строку целиком"
								class="text-gray-400 hover:text-brand-neon p-1 rounded hover:bg-gray-800 transition text-xs">
								📋
							</button>
							<button onclick="deleteItemRow(${index})" title="Удалить товар из корзины" 
								class="text-gray-500 hover:text-rose-400 p-1 rounded hover:bg-rose-950/40 transition text-xs">
								🗑️
							</button>
						</div>
					</td>
				`;
				tbody.appendChild(tr);
			});
		}

		function promptEditItem(index) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			const item = currentMatchData.match.items[index];
			const newName = prompt('Название товара:', item.name);
			if (newName === null) return;
			const newPrice = prompt('Цена за штуку:', item.unit_price);
			if (newPrice === null) return;

			if (newName.trim()) item.name = newName.trim();
			const p = parseFloat(newPrice);
			if (!isNaN(p) && p > 0) item.unit_price = p;

			triggerRecalculate();
			showToast('Товар обновлен.');
		}

		function copyAllProductNames() {
			if (!currentMatchData || !currentMatchData.match.items) return;
			const names = currentMatchData.match.items.map(i => i.name).join('\n');
			navigator.clipboard.writeText(names).then(() => {
				showToast(`Скопировано ${currentMatchData.match.items.length} названий товаров!`);
			});
		}

		function copyCompactList() {
			if (!currentMatchData || !currentMatchData.match) return;
			const list = currentMatchData.compact_list || generateCompactList(currentMatchData.match);
			navigator.clipboard.writeText(list).then(() => {
				showToast('Список товаров с ценами скопирован!');
			});
		}

		function copyTsvData() {
			if (!currentMatchData || !currentMatchData.match) return;
			const tsv = currentMatchData.tsv_data || generateTsvData(currentMatchData.match);
			navigator.clipboard.writeText(tsv).then(() => {
				showToast('Таблица скопирована для вставки в Excel / Google Таблицы!');
			});
		}

		function copySingleItemFullLine(index) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			const item = currentMatchData.match.items[index];
			const curr = currentMatchData.match.currency || 'EUR';
			const line = `${item.name}  [x${item.qty} @ ${Number(item.unit_price).toFixed(2)} ${curr}]  =  ${Number(item.total).toFixed(2)} ${curr}`;
			navigator.clipboard.writeText(line).then(() => {
				showToast(`Строка скопирована: "${item.name}"`);
			});
		}

		function copyText(str) {
			if (!str) return;
			navigator.clipboard.writeText(str).then(() => {
				showToast(`Скопировано: ${str}`);
			});
		}

		function changeItemQty(index, delta) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			const newQty = Number(currentMatchData.match.items[index].qty) + delta;
			if (newQty < 1) return;
			currentMatchData.match.items[index].qty = newQty;
			triggerRecalculate();
		}

		function setItemQty(index, val) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			const qty = Math.max(1, parseInt(val) || 1);
			currentMatchData.match.items[index].qty = qty;
			triggerRecalculate();
		}

		function updateItemName(index, val) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			currentMatchData.match.items[index].name = val.trim();
			triggerRecalculate();
		}

		function updateItemPrice(index, val) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			const price = Math.max(0.01, parseFloat(val) || 0.01);
			currentMatchData.match.items[index].unit_price = price;
			triggerRecalculate();
		}

		function deleteItemRow(index) {
			if (!currentMatchData) return;
			if (currentMatchData.match.items.length <= 1) {
				showToast('В корзине должен оставаться хотя бы 1 товар.', true);
				return;
			}
			currentMatchData.match.items.splice(index, 1);
			triggerRecalculate();
			showToast('Товар удален из корзины.');
		}

		function addNewCustomRow() {
			if (!currentMatchData) return;
			const curr = currentMatchData.match.currency || 'EUR';
			currentMatchData.match.items.push({
				name: 'Custom Product Item',
				sku: '',
				qty: 1,
				unit_price: 25.00,
				total: 25.00,
				total_cents: 2500,
				link: ''
			});
			triggerRecalculate();
			showToast('Добавлен новый товар. Отредактируйте название и цену.');
		}

		async function triggerRecalculate() {
			if (!currentMatchData) return;

			try {
				const res = await fetch('api.php?action=recalculate', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						items: currentMatchData.match.items,
						currency: currentMatchData.match.currency,
						customer: currentMatchData.customer,
						store_name: currentMatchData.store_name,
						shipping: currentMatchData.match.shipping
					})
				});

				const data = await res.json();
				if (data.success) {
					currentMatchData.match = data.match;
					currentMatchData.text_receipt = data.text_receipt;
					currentMatchData.compact_list = data.compact_list;
					currentMatchData.tsv_data = data.tsv_data;
					renderResults(currentMatchData);
				}
			} catch (e) {}
		}

		function copySingleItemName(index) {
			if (!currentMatchData || !currentMatchData.match.items[index]) return;
			const name = currentMatchData.match.items[index].name;
			navigator.clipboard.writeText(name).then(() => {
				showToast(`Название скопировано: "${name}"`);
			});
		}

		function updateCopyHub(data) {
			const textarea = document.getElementById('resCopyTextarea');
			if (currentCopyTab === 'receipt') {
				textarea.value = data.text_receipt || '';
			} else if (currentCopyTab === 'compact') {
				textarea.value = data.compact_list || generateCompactList(data.match);
			} else if (currentCopyTab === 'tsv') {
				textarea.value = data.tsv_data || generateTsvData(data.match);
			} else if (currentCopyTab === 'titles') {
				textarea.value = data.match.items.map(i => i.name).join('\n');
			} else if (currentCopyTab === 'links') {
				textarea.value = data.match.items.map(i => i.link || i.name).join('\n');
			}
		}

		function switchCopyTab(tabName) {
			currentCopyTab = tabName;
			['receipt', 'compact', 'tsv', 'titles', 'links'].forEach(t => {
				const btn = document.getElementById(`tabBtn-${t}`);
				if (t === tabName) {
					btn.className = 'px-2.5 py-1 rounded-lg font-semibold bg-brand-cardBorder text-brand-neon transition';
				} else {
					btn.className = 'px-2.5 py-1 rounded-lg font-semibold text-gray-400 hover:text-white transition';
				}
			});

			if (currentMatchData) {
				updateCopyHub(currentMatchData);
			}
		}

		function copyCurrentTabContent() {
			const textarea = document.getElementById('resCopyTextarea');
			if (!textarea.value) return;
			navigator.clipboard.writeText(textarea.value).then(() => {
				const label = document.getElementById('btnCopyMainLabel');
				label.textContent = 'Скопировано!';
				setTimeout(() => label.textContent = 'Скопировать', 2000);
				showToast('Данные скопированы в буфер обмена!');
			});
		}

		function generateCompactList(match) {
			return match.items.map((i, idx) => `${idx + 1}. ${i.name} [x${i.qty} @ ${Number(i.unit_price).toFixed(2)} ${match.currency}] = ${Number(i.total).toFixed(2)} ${match.currency}`).join('\n');
		}

		function generateTsvData(match) {
			const header = "Product Name\tQuantity\tUnit Price\tTotal\tCurrency\tLink";
			const rows = match.items.map(i => `${i.name}\t${i.qty}\t${Number(i.unit_price).toFixed(2)}\t${Number(i.total).toFixed(2)}\t${match.currency}\t${i.link || ''}`);
			return [header, ...rows].join('\n');
		}

		// ==========================================
		// 👁️ PDF PREVIEW MODAL & ACTIONS
		// ==========================================
		function openPdfPreviewModal() {
			if (!currentMatchData) return;
			document.getElementById('pdfModal').classList.remove('hidden');
			refreshPdfPreviewFrame();
		}

		function closePdfModal() {
			document.getElementById('pdfModal').classList.add('hidden');
		}

		function setBrandColor(colorHex) {
			activeBrandColor = colorHex;
			document.getElementById('customColorPicker').value = colorHex;
			if (currentMatchData) {
				currentMatchData.customer.brand_color = colorHex;
			}
			refreshPdfPreviewFrame();
		}

		function refreshPdfPreviewFrame() {
			if (!currentMatchData) return;
			
			currentMatchData.customer.brand_color = activeBrandColor;
			currentMatchData.customer.payment_method = document.getElementById('editPaymentMethod').value.trim();
			currentMatchData.store_name = document.getElementById('editStoreName').value.trim() || 'STORE';

			const iframe = document.getElementById('pdfPreviewIframe');
			
			fetch('api.php?action=preview_html', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'payload=' + encodeURIComponent(JSON.stringify(currentMatchData))
			})
			.then(res => res.text())
			.then(html => {
				iframe.srcdoc = html;
			});
		}

		function printPdfFromIframe() {
			const iframe = document.getElementById('pdfPreviewIframe');
			if (iframe && iframe.contentWindow) {
				iframe.contentWindow.focus();
				iframe.contentWindow.print();
			}
		}

		function downloadPdfInvoice() {
			if (!currentMatchData) return;

			currentMatchData.customer.brand_color = activeBrandColor;
			currentMatchData.customer.payment_method = document.getElementById('editPaymentMethod').value.trim();
			currentMatchData.store_name = document.getElementById('editStoreName').value.trim() || 'STORE';

			const form = document.createElement('form');
			form.method = 'POST';
			form.action = 'api.php?action=download_pdf';
			form.target = '_blank';

			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'payload';
			input.value = JSON.stringify(currentMatchData);

			form.appendChild(input);
			document.body.appendChild(form);
			form.submit();
			document.body.removeChild(form);
		}

		async function loadSavedShops() {
			try {
				const res = await fetch('api.php?action=list_shops');
				const data = await res.json();
				if (data.success && data.shops) {
					document.getElementById('saved-shops-badge').textContent = data.shops.length;
					renderSavedShopsDropdown(data.shops);
				}
			} catch (e) {}
		}

		function renderSavedShopsDropdown(shops) {
			const list = document.getElementById('savedShopsList');
			list.innerHTML = '';

			if (shops.length === 0) {
				list.innerHTML = '<div class="p-3 text-gray-500 text-center">Нет сохраненных магазинов</div>';
				return;
			}

			shops.forEach(s => {
				const item = document.createElement('div');
				item.className = 'p-2.5 hover:bg-[#1a202c] cursor-pointer flex items-center justify-between transition';
				item.onclick = () => {
					document.getElementById('storeUrl').value = s.url;
					document.getElementById('targetCurrency').value = s.currency || 'EUR';
					toggleSavedShopsDropdown(false);
				};
				item.innerHTML = `
					<div>
						<div class="font-bold text-white">${escapeHtml(s.name)}</div>
						<div class="text-[10px] text-gray-400 truncate max-w-[180px]">${escapeHtml(s.url)}</div>
					</div>
					<div class="text-right">
						<span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-brand-neon/10 text-brand-neon">${s.count} тов.</span>
					</div>
				`;
				list.appendChild(item);
			});
		}

		function toggleSavedShopsDropdown(forceState) {
			const el = document.getElementById('savedShopsDropdown');
			if (typeof forceState === 'boolean') {
				el.classList.toggle('hidden', !forceState);
			} else {
				el.classList.toggle('hidden');
			}
		}

		function refreshSavedShops() {
			loadSavedShops();
			toggleSavedShopsDropdown(true);
		}

		function setLoading(isLoading) {
			const btn = document.getElementById('btnSubmit');
			const text = document.getElementById('btnText');
			const spinner = document.getElementById('btnSpinner');

			btn.disabled = isLoading;
			if (isLoading) {
				text.textContent = 'Сканирование каталога и подбор...';
				spinner.classList.remove('hidden');
				btn.classList.add('opacity-80', 'cursor-not-allowed');
			} else {
				text.textContent = '⚡ Собрать товары под сумму';
				spinner.classList.add('hidden');
				btn.classList.remove('opacity-80', 'cursor-not-allowed');
			}
		}

		function showToast(msg, isError = false) {
			const toast = document.getElementById('toast');
			const toastMsg = document.getElementById('toastMsg');
			const toastIcon = document.getElementById('toastIcon');

			toastMsg.textContent = msg;
			toastIcon.textContent = isError ? '✕' : '✓';

			if (isError) {
				toast.className = 'fixed bottom-6 right-6 z-50 transform transition duration-300 bg-rose-600 text-white font-bold px-4 py-3 rounded-xl shadow-2xl flex items-center gap-2 text-sm';
			} else {
				toast.className = 'fixed bottom-6 right-6 z-50 transform transition duration-300 bg-brand-neon text-black font-bold px-4 py-3 rounded-xl shadow-2xl flex items-center gap-2 text-sm';
			}

			toast.classList.remove('translate-y-20', 'opacity-0');

			setTimeout(() => {
				toast.classList.add('translate-y-20', 'opacity-0');
			}, 3500);
		}

		function escapeHtml(str) {
			if (!str) return '';
			return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
		}
	</script>

<?php endif; ?>

</body>
</html>
