<x-filament-panels::page>
    @php
        $ulStyle = 'list-style:disc; padding-left:1.35rem; margin:0.35rem 0;';
        $liStyle = 'margin:0.2rem 0;';
        $h3Style = 'font-weight:600; font-size:1rem; margin:0.9rem 0 0.3rem;';
        $roleLabels = ['Superadmin' => 'Superadmin', 'Admin' => 'Admin', 'Instructor' => 'Instruktor'];
    @endphp

    {{-- Kdo jsi --}}
    <x-filament::section>
        <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
            <span class="text-gray-500 dark:text-gray-400" style="font-size:0.9rem;">
                Návod se přizpůsobuje tvé roli. Jsi přihlášen(a) jako:
            </span>
            @foreach (auth()->user()->getRoleNames() as $r)
                <span style="display:inline-flex; align-items:center; border-radius:9999px; padding:0.15rem 0.6rem; font-size:0.8rem; font-weight:600; background:#09afe6; color:#fff;">
                    {{ $roleLabels[$r] ?? $r }}
                </span>
            @endforeach
        </div>
    </x-filament::section>

    {{-- ===================== INSTRUKTOR ===================== --}}
    @if ($this->isInstructor())
        <x-filament::section>
            <x-slot name="heading">Portál instruktora – „Moje výlety"</x-slot>

            <p class="text-gray-600 dark:text-gray-300">
                Po přihlášení uvidíš přehled svých nadcházejících výletů (na mobilu jako karty).
                Vidíš jen svoje výlety a svoji odměnu – ne cizí, ne žebříček.
            </p>

            <h3 style="{{ $h3Style }}">Co je na kartě výletu</h3>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Datum (u vícedenního výletu i <em>den X/Y</em> a celý termín) a 🕘 časy <strong>srazu, startu a konce</strong>.</li>
                <li style="{{ $liStyle }}">📍 Úsek dne – nástupní místo → výstupní místo.</li>
                <li style="{{ $liStyle }}">🚐 Přidělená vozidla.</li>
                <li style="{{ $liStyle }}">🏫 Pro koho výlet je (škola / zákazník), 👥 počty (platící, doprovod, věk dětí) a 📞 kontakt na učitele / zákazníka.</li>
                <li style="{{ $liStyle }}">🧑‍🤝‍🧑 S kým ten den jedeš (ostatní instruktoři a řidiči).</li>
                <li style="{{ $liStyle }}">Tvoje role (instruktor / řidič) a 💰 <strong>tvoje odměna</strong> se stavem <em>vyplaceno / zatím nevyplaceno</em>.</li>
            </ul>

            <h3 style="{{ $h3Style }}">Sraz vs. start</h3>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}"><strong>Sraz</strong> = kdy máš dorazit ty. Je dřív než start, aby se stihlo vše nachystat a dopravit na vodu.</li>
                <li style="{{ $liStyle }}"><strong>Start</strong> = kdy výlet reálně začíná (tuto informaci dostává i zákazník).</li>
            </ul>

            <h3 style="{{ $h3Style }}">Poznámky a e-maily</h3>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Tlačítkem <strong>„Přidat poznámku"</strong> nahoře napíšeš poznámku ke konkrétnímu výletu (vidí ji provoz).</li>
                <li style="{{ $liStyle }}">V menu <strong>Kalendář</strong> vidíš svoje výlety po měsících a také <strong>schůze</strong>.</li>
                <li style="{{ $liStyle }}">E-mailem přijde <strong>souhrn</strong> nadcházejících výletů a <strong>připomínka den předem</strong>.</li>
            </ul>
        </x-filament::section>
    @endif

    {{-- ===================== PROVOZ (ADMIN + SUPERADMIN) ===================== --}}
    @if ($this->hasOperations())
        <x-filament::section>
            <x-slot name="heading">Poptávky → převod na výlet</x-slot>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Poptávky chodí z webu (formulář) i je lze zadat ručně. Nové mají v menu odznak s počtem.</li>
                <li style="{{ $liStyle }}">Akce <strong>„Převést na výlet"</strong>: potvrdíš <em>typ, program, termín od, čas startu</em> a počet platících.</li>
                <li style="{{ $liStyle }}"><strong>Termín do</strong> se dopočítá z počtu dní programu; <strong>sraz</strong> a <strong>konec</strong> dne 1 z hodnot programu (sraz = start − offset, konec = start + délka trvání).</li>
                <li style="{{ $liStyle }}">Nástupní a výstupní místa se přiřadí na jednotlivé dny podle pořadí.</li>
                <li style="{{ $liStyle }}">Zákazníkovi se <strong>automaticky odešle potvrzovací e-mail</strong> (obsahuje start, ne sraz).</li>
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Plánování (pondělní schůze)</x-slot>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Tabulka dnů výletů – ve výchozím stavu <strong>14 dní dopředu</strong>. Ve filtru <em>„Od–Do"</em> si zvolíš libovolné období, i zpětně.</li>
                <li style="{{ $liStyle }}"><strong>Klikni na řádek</strong> → otevře se mřížka pro přiřazení <em>instruktorů, řidičů a vozidel</em> + úprava časů (sraz/start/konec) a úseku (nástupní a výstupní místo).</li>
                <li style="{{ $liStyle }}">Vše se ukládá <strong>průběžně</strong> – není potřeba nic potvrzovat.</li>
                <li style="{{ $liStyle }}">Dole v okně přidáváš <strong>poznámky ke dni</strong>.</li>
                <li style="{{ $liStyle }}">Filtr <em>„Jen nepřiřazené"</em> ukáže výlety bez instruktora.</li>
                <li style="{{ $liStyle }}">Tlačítko <strong>„Odeslat souhrnné e-maily"</strong> pošle každému instruktorovi přehled jeho nadcházejících výletů (se srazem i startem).</li>
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Výlety</x-slot>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Seznam má <strong>řádek pro každý den výletu</strong> – třídenní výlet je tam třikrát (<em>den 1/3, 2/3, 3/3</em>) i s úsekem a časem daného dne, ať je vidět, že program pokračuje. Statistiky, odměny a vyúčtování ho pořád počítají jako jeden výlet.</li>
                <li style="{{ $liStyle }}">Ve výchozím stavu ukazuje dny <strong>od dneška dál, nejbližší nahoře</strong>. Filtrem <em>„Termín od–do"</em> zobrazíš libovolné období včetně historie. Proběhlé dny jsou ztlumené.</li>
                <li style="{{ $liStyle }}"><strong>Smazat</strong> u vícedenního výletu se zeptá, zda smazat celý výlet, nebo jen ten den (termín výletu se pak sám zkrátí). Hromadné mazání maže vždy celé výlety.</li>
                <li style="{{ $liStyle }}">Zvolené řazení a filtry si seznam pamatuje, dokud se neodhlásíš. Sloupce lze skrýt a přeskládat (ikona sloupců nad tabulkou).</li>
                <li style="{{ $liStyle }}"><strong>Změna termínu</strong> posune i dny výletu (časy, místa a lidé zůstanou podle pořadí dne). Při zkrácení se přebývající dny smažou. Výlet může mít nejvýš 31 dní.</li>
                <li style="{{ $liStyle }}">Detail výletu obsahuje formulář (typ, program, termín, klient, <em>vyúčtování</em>, u půjčovny vybavení) a záložky:</li>
                <li style="{{ $liStyle }}"><strong>Dny výletu</strong> – přiřazení, časy, nástupní a výstupní místo, vozidla (jako v plánování).</li>
                <li style="{{ $liStyle }}"><strong>Poznámky</strong> – komentářové vlákno.</li>
                @if ($this->isSuperadmin())
                    <li style="{{ $liStyle }}"><strong>Historie změn (audit)</strong> – kdo co kdy změnil (jen Superadmin).</li>
                @endif
                <li style="{{ $liStyle }}"><strong>Typy:</strong> „Akce s instruktorem" (program + odměny) nebo „Půjčovna" (cena = vybavení × dny, bez instruktora; ruční přepis ceny vypne automatický přepočet).</li>
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Kalendář a schůze</x-slot>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}"><strong>Kalendář</strong> ukazuje dny výletů po měsících. Zaškrtnutím <em>„Jen moje výlety"</em> uvidíš jen ty, kde jsi přiřazený(á).</li>
                <li style="{{ $liStyle }}"><strong>Schůze</strong> zakládáš v menu Provoz → Schůze (název, datum, čas, místo, poznámka). V kalendáři je vidí všichni včetně instruktorů.</li>
                <li style="{{ $liStyle }}">Instruktoři v kalendáři vidí jen svoje výlety.</li>
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Platby</x-slot>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Seznam lidí s <strong>nevyplacenými odměnami za proběhlé dny</strong> (datum v minulosti), s celkovou dlužnou částkou.</li>
                <li style="{{ $liStyle }}"><strong>Detail</strong> = rozpis po výletech. Můžeš <strong>„Zaplatit vše"</strong> nebo jednotlivý výlet.</li>
                <li style="{{ $liStyle }}">Odměna: <strong>instruktor</strong> = plná odměna výletu (z programu), <strong>řidič</strong> = 0. Lze ručně upravit u přiřazení.</li>
                <li style="{{ $liStyle }}">Prázdná odměna programu = „dle domluvy" – počítá se jako 0, dořeš ručně.</li>
            </ul>
        </x-filament::section>
    @endif

    {{-- ===================== SPRÁVA (SUPERADMIN) ===================== --}}
    @if ($this->isSuperadmin())
        <x-filament::section>
            <x-slot name="heading">Číselníky a nastavení (jen Superadmin)</x-slot>

            <h3 style="{{ $h3Style }}">Programy (ceník)</h3>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">Cena za osobu a odměna instruktora.</li>
                <li style="{{ $liStyle }}"><strong>Počet dní</strong> – určuje délku výletu (termín do).</li>
                <li style="{{ $liStyle }}"><strong>Délka trvání (min)</strong> – z ní se dopočítá konec (start + trvání).</li>
                <li style="{{ $liStyle }}"><strong>Sraz před startem (min)</strong> – z něj se dopočítá sraz (start − minuty). Výchozí 50.</li>
                <li style="{{ $liStyle }}"><strong>Úseky po dnech</strong> – nástupní a výstupní místo každého dne (přiřadí se při převodu poptávky / založení výletu).</li>
                <li style="{{ $liStyle }}">⚠️ <strong>Název musí přesně odpovídat titulku stránky výletu na webu</strong> – podle něj se párují poptávky z formuláře.</li>
            </ul>

            <h3 style="{{ $h3Style }}">Další číselníky</h3>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}"><strong>Vybavení (půjčovna)</strong> – cena za den/kus.</li>
                <li style="{{ $liStyle }}"><strong>Lokace</strong> – seznam nástupních a výstupních míst (název + aktivní).</li>
                <li style="{{ $liStyle }}"><strong>Vozidla</strong> – název, SPZ, kapacita.</li>
            </ul>

            <h3 style="{{ $h3Style }}">Uživatelé, e-maily, žebříček</h3>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}"><strong>Uživatelé a instruktoři</strong> – role (Superadmin / Admin / Instruktor) a příznak „může řídit".</li>
                <li style="{{ $liStyle }}"><strong>Šablony e-mailů</strong> – potvrzení poptávky (zákazník), souhrn a připomínka (instruktoři). Používají makra <code>@{{makro}}</code>, která se před odesláním nahradí; dostupná makra jsou vypsaná ve formuláři šablony.</li>
                <li style="{{ $liStyle }}"><strong>Žebříček instruktorů</strong> – pořadí dle odjetých dnů + celková a vyplacená odměna, filtr obdobím.</li>
            </ul>
        </x-filament::section>
    @endif

    {{-- ===================== ROLE ===================== --}}
    <x-filament::section>
        <x-slot name="heading">Role a oprávnění</x-slot>
        <ul style="{{ $ulStyle }}">
            <li style="{{ $liStyle }}"><strong>Superadmin</strong> – vše: provoz i číselníky, uživatelé, šablony, žebříček, audit.</li>
            <li style="{{ $liStyle }}"><strong>Admin</strong> – provoz (výlety, poptávky, plánování, kalendář, schůze, platby). Nemá číselníky, uživatele ani žebříček.</li>
            <li style="{{ $liStyle }}"><strong>Instruktor</strong> – svůj portál „Moje výlety" a kalendář se svými výlety a schůzemi.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
