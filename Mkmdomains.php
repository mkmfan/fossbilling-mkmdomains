<?php
/**
 * MKM Domains Registrar Adapter for FOSSBilling
 * File: src/library/Registrar/Adapter/Mkmdomains.php
 * Class: Registrar_Adapter_Mkmdomains
 * API:  https://mkm.fan/system/registry.php  (API v2 / v2.1 / v2.2)
 *
 * Works for ANY reseller FOSSBilling install (MKM's own billing, Hostingo,
 * or any future reseller): endpoint + API key are configured per install.
 * Works for ANY zone of MKM Domains (mkm.fan and future zones)
 * - the zone is taken from the domain TLD automatically, so adding a new
 * zone later only requires a new TLD in FOSSBilling + a zone approval on your key.
 */

class Registrar_Adapter_Mkmdomains extends Registrar_AdapterAbstract
{
    private string $_endpoint;
    private string $_key;
    private string $_defaultNs1;
    private string $_defaultNs2;

    public function __construct($options)
    {
        // FOSSBilling pattern: no exceptions in the constructor
        // (otherwise the settings page crashes). The key is checked in _api().
        $this->_endpoint   = trim((string)($options['api_endpoint'] ?? 'https://mkm.fan/system/registry.php'));
        $this->_key        = trim((string)($options['api_key'] ?? ''));
        $this->_defaultNs1 = trim((string)($options['default_ns1'] ?? 'ns1.mkm.fan'));
        $this->_defaultNs2 = trim((string)($options['default_ns2'] ?? 'ns2.mkm.fan'));
    }

    public static function getConfig(): array
    {
        return [
            'label' => 'MKM Domains',
            'form'  => [
                'api_endpoint' => ['text', [
                    'label'       => 'API Endpoint',
                    'description' => 'Full URL, e.g. https://mkm.fan/system/registry.php',
                ]],
                'api_key' => ['password', [
                    'label'       => 'API Key',
                    'description' => 'The mkm_live_... / mkm_admin_... key issued for this reseller (MKM Domains)',
                    'secret'      => true,
                ]],
                'default_ns1' => ['text', ['label' => 'Default Nameserver 1']],
                'default_ns2' => ['text', ['label' => 'Default Nameserver 2']],
            ],
        ];
    }

    // ─────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────
    private function _api(array $payload): array
    {
        if ($this->_key === '') {
            throw new Registrar_Exception('MKM Domains: API Key is required. Save the key in the registrar settings.');
        }
        $ch = curl_init($this->_endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Registry-Key: ' . $this->_key,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Registrar_Exception('MKM Domains API connection failed: ' . $err);
        }
        curl_close($ch);
        $data = json_decode((string)$res, true);
        if (!is_array($data)) {
            throw new Registrar_Exception('MKM Domains API: invalid response');
        }
        return $data;
    }

    private function _zone(Registrar_Domain $domain): string
    {
        return ltrim(strtolower($domain->getTld()), '.');
    }

    // ─────────────────────────────────────────
    // Availability
    // ─────────────────────────────────────────
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $r = $this->_api([
            'action' => 'check',
            'label'  => $domain->getSld(),
            'zone'   => $this->_zone($domain),
        ]);
        $res = $r['result'] ?? [];

        if (!empty($res['available'])) {
            $tier = $res['pricing']['tier'] ?? 'standard';
            if ($tier === 'standard') {
                return true;
            }
            // 1/2/3-char and premium words have separate rates - not sold through this module
            throw new Registrar_Exception(
                'This name is in the premium category (' . $tier . ') and is priced separately. Please order it directly from the shop support.'
            );
        }

        throw new Registrar_Exception('Domain is not available');
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        return false; // transfers do not apply to these subdomain zones
    }

    // ─────────────────────────────────────────
    // Register / Renew / Delete / Transfer
    // ─────────────────────────────────────────
    public function registerDomain(Registrar_Domain $domain)
    {
        $contact = $domain->getContactRegistrar();
        $email   = ($contact instanceof Registrar_Domain_Contact) ? $contact->getEmail() : '';

        $payload = [
            'action'     => 'register',
            'label'      => $domain->getSld(),
            'zone'       => $this->_zone($domain),
            'user_email' => $email,
            'site_title' => $domain->getName(),
        ];

        if ($domain->getNs1()) {
            // the customer provided nameservers -> NS delegation mode
            $payload['dns_mode']     = 'ns';
            $payload['target_value'] = $domain->getNs1();
        } else {
            // otherwise MKM Domains DNS is used
            $payload['dns_mode']     = 'internal';
            $payload['target_value'] = null;
        }

        $r = $this->_api($payload);
        if (!empty($r['ok'])) {
            return true;
        }
        throw new Registrar_Exception('MKM Domains: ' . ($r['error'] ?? ($r['message'] ?? 'Registration failed')));
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $years = max(1, (int)($domain->getRegistrationPeriod() ?? 1));

        $r = $this->_api([
            'action' => 'renew',
            'label'  => $domain->getSld(),
            'zone'   => $this->_zone($domain),
            'years'  => $years,
        ]);
        if (!empty($r['ok']) && !empty($r['renewed'])) {
            return true;
        }
        throw new Registrar_Exception('MKM Domains: ' . ($r['error'] ?? 'Renewal failed (domain not found?)'));
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $r = $this->_api([
            'action' => 'release',
            'label'  => $domain->getSld(),
            'zone'   => $this->_zone($domain),
        ]);
        if (!empty($r['ok']) && !empty($r['released'])) {
            return true;
        }
        throw new Registrar_Exception('MKM Domains: ' . ($r['error'] ?? 'Release failed'));
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        // a subdomain "transfer" = registering it again (if available)
        return $this->registerDomain($domain);
    }

    // ─────────────────────────────────────────
    // Details / NS / Contact
    // ─────────────────────────────────────────
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $r = $this->_api([
            'action' => 'whois',
            'label'  => $domain->getSld(),
            'zone'   => $this->_zone($domain),
        ]);
        if (empty($r['registered'])) {
            throw new Registrar_Exception('MKM Domains: domain not found');
        }
        $rec = $r['record'] ?? [];

        if (!empty($rec['expires_at'])) {
            $domain->setExpirationTime(strtotime($rec['expires_at']));
        }
        if (!empty($rec['created_at'])) {
            $domain->setRegistrationTime(strtotime($rec['created_at']));
        }
        $domain->setPrivacyEnabled(false);
        $domain->setLocked(false);

        return $domain;
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $ns1 = $domain->getNs1();
        if (!$ns1) {
            throw new Registrar_Exception('MKM Domains: no nameservers provided');
        }
        $r = $this->_api([
            'action' => 'update_ns',
            'label'  => $domain->getSld(),
            'zone'   => $this->_zone($domain),
            'ns1'    => $ns1,
            'ns2'    => $domain->getNs2(),
        ]);
        if (!empty($r['ok'])) {
            return true;
        }
        throw new Registrar_Exception('MKM Domains: ' . ($r['error'] ?? 'Nameserver update failed'));
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        // FOSSBilling keeps the contact details in its own DB - no need to send them to MKM Domains
        return true;
    }

    // ─────────────────────────────────────────
    // Not-applicable features (subdomain zones)
    // ─────────────────────────────────────────
    public function getEpp(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('EPP / auth codes do not apply to these domains.');
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        return true; // no public WHOIS for these domains - privacy is built in
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        return true;
    }

    public function lock(Registrar_Domain $domain)
    {
        return true; // managed from the MKM Domains panel
    }

    public function unlock(Registrar_Domain $domain)
    {
        return true;
    }
}
