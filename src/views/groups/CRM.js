import React from "react";
import { Card, CardHeader, CardBody, Container, Row, Col, Table, Badge, Form, Input, Button } from "reactstrap";
import Header from "components/Headers/Header.js";
import { useLocation } from "react-router-dom";

const CRM = () => {
  const [opps, setOpps] = React.useState([]);
  const [contacts, setContacts] = React.useState([]);
  const [summary, setSummary] = React.useState(null);
  const [status, setStatus] = React.useState("");
  const [limitOpp, setLimitOpp] = React.useState(20);
  const [offsetOpp, setOffsetOpp] = React.useState(0);
  const [limitCont, setLimitCont] = React.useState(20);
  const [offsetCont, setOffsetCont] = React.useState(0);
  const statuses = ["","novo","andamento","ganho","perdido"];
  const [scans, setScans] = React.useState([]);
  const [audits, setAudits] = React.useState([]);
  const location = useLocation();
  const mode = React.useMemo(()=>{
    const p = location.pathname || "";
    if (p.includes('/crm/ssl')) return 'ssl';
    if (p.includes('/crm/auditorias')) return 'aud';
    return 'opps';
  }, [location.pathname]);
  const fetchOpps = async (reset=false) => {
    try {
      let url = "/api/crm/oportunidades";
      const params = new URLSearchParams();
      if (status) { params.set("status", status); params.set("limit", String(limitOpp)); }
      else { params.set("limit", String(limitOpp)); params.set("offset", String(reset?0:offsetOpp)); }
      url += `?${params.toString()}`;
      const r = await fetch(url);
      const j = await r.json();
      setOpps(j.items || []);
      if (reset) setOffsetOpp(0);
    } catch (e) {}
  };
  const fetchContacts = async (reset=false) => {
    try {
      const params = new URLSearchParams();
      params.set("limit", String(limitCont));
      params.set("offset", String(reset?0:offsetCont));
      const r = await fetch(`/api/crm/contatos?${params.toString()}`);
      const j = await r.json();
      setContacts(j.items || []);
      if (reset) setOffsetCont(0);
    } catch (e) {}
  };
  React.useEffect(() => {
    const run = async () => {
      try { const r = await fetch('/api/crm/summary'); const j = await r.json(); setSummary(j); } catch(e){}
      if (mode === 'opps') { fetchOpps(true); fetchContacts(true); }
      else if (mode === 'ssl') {
        try { const r = await fetch('/api/ssl/scans/list?limit=50'); const j = await r.json(); setScans(j.items || []); } catch(e){}
      } else if (mode === 'aud') {
        try { const r = await fetch('/api/audit/list?limit=50'); const j = await r.json(); setAudits(j.items || []); } catch(e){}
      }
    };
    run();
  }, [mode]);
  return (
    <>
      <Header />
      <Container className="mt--7" fluid>
        {summary && (
          <Row className="mb-4">
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Oportunidades</p><h5 className="card-title">{summary.oportunidades_total}</h5></div></div></div></Col>
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Novas</p><h5 className="card-title">{summary.oportunidades_novo}</h5></div></div></div></Col>
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Andamento</p><h5 className="card-title">{summary.oportunidades_andamento}</h5></div></div></div></Col>
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Contatos</p><h5 className="card-title">{summary.contatos_total}</h5></div></div></div></Col>
          </Row>
        )}
        {mode === 'opps' && (
        <Row>
          <Col md="12">
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">CRM — Oportunidades</h3>
              </CardHeader>
              <CardBody>
                <Form inline className="mb-3" onSubmit={(e)=>{e.preventDefault(); fetchOpps(true);}}>
                  <Row className="w-100">
                    <Col md="6" className="mb-2">
                      <Input type="select" value={status} onChange={(e)=>setStatus(e.target.value)}>
                        {statuses.map((s)=>(<option key={s} value={s}>{s || "Status"}</option>))}
                      </Input>
                    </Col>
                    <Col md="6" className="mb-2 text-right">
                      <Button color="primary" onClick={()=>fetchOpps(true)}>Filtrar</Button>
                    </Col>
                  </Row>
                </Form>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Título</th>
                      <th>Status</th>
                      <th>Origem</th>
                      <th>Prioridade</th>
                      <th>Criado em</th>
                    </tr>
                  </thead>
                  <tbody>
                    {opps.map((o) => (
                      <tr key={o.id}>
                        <td>{o.titulo}</td>
                        <td><Badge color="info">{o.status}</Badge></td>
                        <td>{o.origem}</td>
                        <td>{o.prioridade || "-"}</td>
                        <td>{new Date(o.created_at).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <div className="d-flex justify-content-between align-items-center mt-3">
                  <div>
                    <Button color="secondary" onClick={()=>{ if(offsetOpp>=limitOpp){ setOffsetOpp(offsetOpp-limitOpp); fetchOpps(); } }} disabled={offsetOpp===0 || !!status}>Anterior</Button>{" "}
                    <Button color="secondary" onClick={()=>{ setOffsetOpp(offsetOpp+limitOpp); fetchOpps(); }} disabled={!!status}>Próximo</Button>
                  </div>
                  <div>
                    <Input type="select" value={limitOpp} onChange={(e)=>{ setLimitOpp(parseInt(e.target.value||"20",10)); fetchOpps(true); }} style={{width:120}}>
                      {[10,20,50].map(n=>(<option key={n} value={n}>{n}/página</option>))}
                    </Input>
                  </div>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>
        )}
        {mode === 'opps' && (
        <Row className="mt-4">
          <Col md="12">
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">CRM — Contatos</h3>
              </CardHeader>
              <CardBody>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Nome</th>
                      <th>Cargo</th>
                      <th>Email</th>
                      <th>Telefone</th>
                      <th>Origem</th>
                    </tr>
                  </thead>
                  <tbody>
                    {contacts.map((c) => (
                      <tr key={c.id}>
                        <td>{c.nome}</td>
                        <td>{c.cargo || "-"}</td>
                        <td>{c.email || "-"}</td>
                        <td>{c.telefone || "-"}</td>
                        <td><Badge color="primary">{c.origem}</Badge></td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <div className="d-flex justify-content-between align-items-center mt-3">
                  <div>
                    <Button color="secondary" onClick={()=>{ if(offsetCont>=limitCont){ setOffsetCont(offsetCont-limitCont); fetchContacts(); } }}>Anterior</Button>{" "}
                    <Button color="secondary" onClick={()=>{ setOffsetCont(offsetCont+limitCont); fetchContacts(); }}>Próximo</Button>
                  </div>
                  <div>
                    <Input type="select" value={limitCont} onChange={(e)=>{ setLimitCont(parseInt(e.target.value||"20",10)); fetchContacts(true); }} style={{width:120}}>
                      {[10,20,50].map(n=>(<option key={n} value={n}>{n}/página</option>))}
                    </Input>
                  </div>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>
        )}

        {mode === 'ssl' && (
        <Row>
          <Col md="12">
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">CRM — SSL Scans</h3>
              </CardHeader>
              <CardBody>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Órgão</th>
                      <th>UF</th>
                      <th>Domínio</th>
                      <th>Status</th>
                      <th>Validade</th>
                      <th>Dias</th>
                      <th>Scan em</th>
                    </tr>
                  </thead>
                  <tbody>
                    {scans.map((s, idx) => (
                      <tr key={idx}>
                        <td>{s.nome || '-'}</td>
                        <td>{s.uf || '-'}</td>
                        <td>{s.dominio}</td>
                        <td><Badge color={s.status==='expired'?'danger':(s.status==='valid'?'success':'secondary')}>{s.status}</Badge></td>
                        <td>{s.valid_to ? new Date(s.valid_to).toLocaleDateString() : '-'}</td>
                        <td>{typeof s.dias_restantes === 'number' ? s.dias_restantes : '-'}</td>
                        <td>{s.last_scan_at ? new Date(s.last_scan_at).toLocaleString() : '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </CardBody>
            </Card>
          </Col>
        </Row>
        )}

        {mode === 'aud' && (
        <Row>
          <Col md="12">
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">CRM — Auditorias</h3>
              </CardHeader>
              <CardBody>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Entidade</th>
                      <th>ID Entidade</th>
                      <th>Ação</th>
                      <th>Payload</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    {audits.map((a)=> (
                      <tr key={a.id}>
                        <td>{a.entity_type}</td>
                        <td>{a.entity_id}</td>
                        <td><Badge color="info">{a.action}</Badge></td>
                        <td style={{maxWidth:300, overflow:'hidden', textOverflow:'ellipsis'}}>{a.payload_json || '-'}</td>
                        <td>{new Date(a.created_at).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </CardBody>
            </Card>
          </Col>
        </Row>
        )}
      </Container>
    </>
  );
};

export default CRM;
