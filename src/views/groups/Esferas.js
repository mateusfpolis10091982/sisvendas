import React from "react";
import { Card, CardHeader, CardBody, Container, Row, Col, Table, Form, Input, InputGroup, InputGroupAddon, InputGroupText, Button, Badge } from "reactstrap";
import Header from "components/Headers/Header.js";

const Esferas = () => {
  const [items, setItems] = React.useState([]);
  const [esfera, setEsfera] = React.useState("");
  const [uf, setUf] = React.useState("");
  const [tipo, setTipo] = React.useState("");
  const [limit, setLimit] = React.useState(20);
  const [offset, setOffset] = React.useState(0);
  const [loading, setLoading] = React.useState(false);
  const [hasDominio, setHasDominio] = React.useState(true);
  const [sslStatus, setSslStatus] = React.useState("");
  const ufList = ["","AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"];

  const statusColor = (s) => {
    if (s === "expired") return "danger";
    if (s === "valid") return "success";
    if (s === "unknown") return "secondary";
    return "info";
  };

  const fetchItems = async (reset = false) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set("limit", String(limit));
      params.set("offset", String(reset ? 0 : offset));
      if (esfera) params.set("esfera", esfera);
      if (uf) params.set("uf", uf);
      if (tipo) params.set("tipo", tipo);
      if (hasDominio) params.set("has_dominio", "1");
      if (sslStatus) { params.set("has_ssl","1"); params.set("ssl_status", sslStatus); }
      const res = await fetch(`/api/orgaos/search?${params.toString()}`);
      const j = await res.json();
      setItems(j.items || []);
      if (reset) setOffset(0);
    } catch (e) {}
    setLoading(false);
  };

  const enrich = async () => { try { await fetch('/api/orgaos/enrich'); fetchItems(true); } catch(e){} };
  const resolveMissing = async () => { try { const body = { uf: uf || undefined, esfera: esfera || undefined, limit: 200 }; await fetch('/api/domains/resolve_missing', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) }); fetchItems(true); } catch(e){} };
  const scanSSL = async () => { try { const body = { uf: uf || undefined, esfera: esfera || undefined, limit: 200 }; await fetch('/api/ssl/scan_criteria', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) }); fetchItems(); } catch(e){} };

  React.useEffect(() => { fetchItems(true); }, []);

  return (
    <>
      <Header />
      <Container className="mt--7" fluid>
        <Row>
          <Col>
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">Órgãos do Governo (Esferas)</h3>
              </CardHeader>
              <CardBody>
                <Form inline className="mb-3" onSubmit={(e)=>{e.preventDefault(); fetchItems(true);}}>
                  <Row className="w-100">
                    <Col md="3" className="mb-2">
                      <Input type="select" value={esfera} onChange={(e)=>setEsfera(e.target.value)}>
                        {["","municipal","estadual","federal"].map((v)=>(<option key={v} value={v}>{v || "Esfera"}</option>))}
                      </Input>
                    </Col>
                    <Col md="3" className="mb-2">
                      <Input type="select" value={uf} onChange={(e)=>setUf(e.target.value)}>
                        {ufList.map((u)=>(<option key={u} value={u}>{u || "UF"}</option>))}
                      </Input>
                    </Col>
                    <Col md="3" className="mb-2">
                      <Input placeholder="Tipo (ex.: prefeitura)" value={tipo} onChange={(e)=>setTipo(e.target.value)} />
                    </Col>
                    <Col md="3" className="mb-2">
                      <Input type="select" value={sslStatus} onChange={(e)=>setSslStatus(e.target.value)}>
                        {["","valid","expired","unknown"].map(s=>(<option key={s} value={s}>{s||"SSL Status"}</option>))}
                      </Input>
                    </Col>
                  </Row>
                  <Row className="w-100 mt-2">
                    <Col md="3" className="mb-2">
                      <Input type="select" value={hasDominio?"1":"0"} onChange={(e)=>setHasDominio(e.target.value === "1")}>
                        {["1","0"].map(v=>(<option key={v} value={v}>{v==="1"?"Com domínio":"Sem domínio"}</option>))}
                      </Input>
                    </Col>
                    <Col md="3" className="mb-2">
                      <Button color="primary" onClick={()=>fetchItems(true)} disabled={loading}>Buscar</Button>
                    </Col>
                    <Col md="2" className="mb-2">
                      <Button color="info" onClick={resolveMissing} disabled={loading}>Validar IA</Button>
                    </Col>
                    <Col md="2" className="mb-2">
                      <Button color="warning" onClick={scanSSL} disabled={loading}>Rastrear SSL</Button>
                    </Col>
                    <Col md="2" className="mb-2">
                      <Button color="success" onClick={enrich} disabled={loading}>Enriquecer</Button>
                    </Col>
                  </Row>
                  <Row className="w-100 mt-2">
                    <Col md="4" className="mb-2">
                      <Button color="primary" onClick={async()=>{ try { const r = await fetch('/api/ia/pipeline/detect', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ uf: uf || undefined, limit: 50 }) }); await r.json(); fetchItems(true); } catch(e){} }}>Detecção Nacional (Municipal)</Button>
                    </Col>
                    <Col md="4" className="mb-2">
                      <Button color="primary" onClick={async()=>{ if(!uf) return; try { const r = await fetch(`/api/ia/pipeline/detect_estadual?uf=${uf}`, { method:'POST' }); await r.json(); fetchItems(true); } catch(e){} }}>Detecção Estadual (UF)</Button>
                    </Col>
                    <Col md="4" className="mb-2">
                      <Button color="primary" onClick={async()=>{ try { const r = await fetch('/api/ia/pipeline/detect_federal', { method:'POST' }); await r.json(); fetchItems(true); } catch(e){} }}>Detecção Federal</Button>
                    </Col>
                  </Row>
                </Form>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Tipo</th>
                      <th>Nome</th>
                      <th>UF</th>
                      <th>Domínio</th>
                      <th>Esfera</th>
                      <th>SSL</th>
                      <th>Validade</th>
                      <th>Dias</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {!loading && items.map((o) => (
                      <tr key={o.id}>
                        <td>{o.tipo}</td>
                        <td>{o.nome}</td>
                        <td>{o.uf}</td>
                        <td>{o.dominio || "-"}</td>
                        <td>{o.esfera || "-"}</td>
                        <td>{o.ssl_status ? (<Badge color={statusColor(o.ssl_status)}>{o.ssl_status}</Badge>) : ("-")}</td>
                        <td>{o.valid_to ? new Date(o.valid_to).toLocaleDateString() : "-"}</td>
                        <td>{typeof o.dias_restantes === 'number' ? o.dias_restantes : '-'}</td>
                        <td>
                          <Button size="sm" color="info" onClick={async()=>{ try { const res = await fetch(`/api/ia/analyze-orgao?id=${o.id}`); await res.json(); } catch(e){} }}>Criar Opp</Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <div className="d-flex justify-content-between align-items-center mt-3">
                  <div>
                    <Button color="secondary" onClick={()=>{ if(offset>=limit){ setOffset(offset-limit); fetchItems(); } }} disabled={offset===0 || loading}>Anterior</Button>{" "}
                    <Button color="secondary" onClick={()=>{ setOffset(offset+limit); fetchItems(); }} disabled={loading}>Próximo</Button>
                  </div>
                  <div>
                    <Input type="select" value={limit} onChange={(e)=>{ setLimit(parseInt(e.target.value||"20",10)); fetchItems(true); }} style={{width:120}}>
                      {[10,20,50].map(n=>(<option key={n} value={n}>{n}/página</option>))}
                    </Input>
                  </div>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </>
  );
};

export default Esferas;
