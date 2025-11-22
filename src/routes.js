/*!

=========================================================
* Argon Dashboard React - v1.2.4
=========================================================

* Product Page: https://www.creative-tim.com/product/argon-dashboard-react
* Copyright 2024 Creative Tim (https://www.creative-tim.com)
* Licensed under MIT (https://github.com/creativetimofficial/argon-dashboard-react/blob/master/LICENSE.md)

* Coded by Creative Tim

=========================================================

* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

*/
import Index from "views/Index.js";
import Profile from "views/examples/Profile.js";
import Register from "views/examples/Register.js";
import Login from "views/examples/Login.js";
import Certificados from "views/groups/Certificados.js";
import CRM from "views/groups/CRM.js";
import MunicipiosOrgaos from "views/groups/MunicipiosOrgaos.js";
import Pipelines from "views/groups/Pipelines.js";
import Sistema from "views/groups/Sistema.js";
import Esferas from "views/groups/Esferas.js";

var routes = [
  {
    path: "/index",
    name: "Dashboard",
    icon: "ni ni-tv-2 text-primary",
    component: <Index />,
    layout: "/admin",
  },
  {
    path: "/certificados",
    name: "Certificados",
    icon: "ni ni-lock-circle text-blue",
    component: <Certificados />,
    layout: "/admin",
  },
  {
    path: "/crm",
    name: "CRM",
    icon: "ni ni-collection text-orange",
    component: <CRM />,
    layout: "/admin",
  },
  {
    path: "/crm/opps",
    name: "CRM: Oportunidades",
    icon: "ni ni-bullet-list-67 text-orange",
    component: <CRM />,
    layout: "/admin",
    group: "CRM",
  },
  {
    path: "/crm/ssl",
    name: "CRM: SSL Scans",
    icon: "ni ni-lock-circle text-orange",
    component: <CRM />,
    layout: "/admin",
    group: "CRM",
  },
  {
    path: "/crm/auditorias",
    name: "CRM: Auditorias",
    icon: "ni ni-check-bold text-orange",
    component: <CRM />,
    layout: "/admin",
    group: "CRM",
  },
  {
    path: "/municipios",
    name: "Municípios & Órgãos",
    icon: "ni ni-building text-yellow",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
  },
  {
    path: "/orgaos/buscar",
    name: "Buscar Órgão",
    icon: "ni ni-zoom-split-in text-yellow",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/recentes",
    name: "Órgãos Recentes",
    icon: "ni ni-time-alarm text-yellow",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/esferas",
    name: "Esferas de Governo 🔥",
    icon: "ni ni-building text-orange",
    component: <Esferas />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/prefeituras",
    name: "Prefeituras por UF",
    icon: "ni ni-building text-blue",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/camaras",
    name: "Câmaras Municipais",
    icon: "ni ni-paper-diploma text-blue",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/secretarias-municipais",
    name: "Secretarias Municipais",
    icon: "ni ni-badge text-blue",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/secretarias-estaduais",
    name: "Secretarias Estaduais",
    icon: "ni ni-badge text-orange",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/autarquias",
    name: "Autarquias",
    icon: "ni ni-building text-green",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/empresas-publicas",
    name: "Empresas Públicas",
    icon: "ni ni-shop text-green",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/fundacoes",
    name: "Fundações",
    icon: "ni ni-building text-teal",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/tribunais",
    name: "Tribunais",
    icon: "ni ni-collection text-teal",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/orgaos/ministerios",
    name: "Ministérios",
    icon: "ni ni-building text-purple",
    component: <MunicipiosOrgaos />,
    layout: "/admin",
    group: "Órgãos Públicos",
  },
  {
    path: "/pipelines",
    name: "Pipelines",
    icon: "ni ni-send text-red",
    component: <Pipelines />,
    layout: "/admin",
  },
  {
    path: "/sistema",
    name: "Sistema",
    icon: "ni ni-settings-gear-65 text-purple",
    component: <Sistema />,
    layout: "/admin",
  },
  {
    path: "/login",
    name: "Login",
    icon: "ni ni-key-25 text-info",
    component: <Login />,
    layout: "/auth",
  },
  {
    path: "/register",
    name: "Register",
    icon: "ni ni-circle-08 text-pink",
    component: <Register />,
    layout: "/auth",
  },
];
export default routes;
