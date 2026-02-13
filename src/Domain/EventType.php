<?php

declare(strict_types=1);

namespace CrmStages\Domain;

/**
 * Типы событий CRM.
 */
enum EventType: string
{
    case ContactAttempt      = 'contact_attempt';
    case LprConversation     = 'lpr_conversation';
    case DiscoveryFilled     = 'discovery_filled';
    case DemoPlanned         = 'demo_planned';
    case DemoConducted       = 'demo_conducted';
    case InvoiceIssued       = 'invoice_issued';
    case PaymentReceived     = 'payment_received';
    case CertificateIssued   = 'certificate_issued';
    case ApplicationCreated  = 'application_created';
    case CpSent              = 'cp_sent';

    public function label(): string
    {
        return match ($this) {
            self::ContactAttempt     => 'Попытка контакта',
            self::LprConversation    => 'Разговор с ЛПР',
            self::DiscoveryFilled    => 'Заполнение дискавери',
            self::DemoPlanned        => 'Планирование демо',
            self::DemoConducted      => 'Демо проведено',
            self::InvoiceIssued      => 'Счёт выставлен',
            self::PaymentReceived    => 'Оплата получена',
            self::CertificateIssued  => 'Удостоверение выдано',
            self::ApplicationCreated => 'Заявка заведена',
            self::CpSent             => 'КП отправлено',
        };
    }
}
